<?php

/**
 * Backfills `usctdp_student.level` (and `usctdp_registration.student_level`
 * for every existing registration of that student) from the legacy JSON's
 * per-registration `level` field. Every student created since the switch to
 * self-service accounts (Usctdp_Mgmt_Woocommerce_Hooks::create_student() /
 * create_family_on_registration(), Usctdp_Stage_Legacy_Families's confirmed-
 * import path) starts with level = '' - this is the one-time pass that
 * fills that in from the old system for families who already re-registered
 * on their own, matched up by name rather than by any shared id (the
 * legacy data and the new accounts have no id in common - see
 * Usctdp_Import_Family_Data's separate direct-write importer, which was
 * never used for these accounts).
 *
 * Legacy shape: each family has `regs[]`, one entry per historical
 * transaction (class registration, but also billing statements/late fees
 * mixed in with blank `level`/`session`). There's no per-child level field -
 * only per-registration - so "current level" for a legacy child means the
 * most recent (by txn_date) reg entry for that child with a usable level.
 *
 * Matching is inherently fuzzy in both directions:
 *   - reg -> child within a legacy family: `regs[].name` frequently isn't
 *     an exact `children[].name` (e.g. "Ethan-5wks.", "Andrew - 4wks.",
 *     nicknames/misspellings like "Bill" or "Mickal"). Resolved by
 *     stripping anything from the first "-" onward and matching what's
 *     left; a family with exactly one child never needs this since there's
 *     nothing to disambiguate.
 *   - legacy family/child -> real usctdp_family/usctdp_student: matched by
 *     normalized last name (tried against both `family.last` and the name
 *     portion of `family.id`, since the two disagree on spelling ~2% of the
 *     time - see class doc comment history) with a small Levenshtein
 *     fallback for typos, then by first name within that family. Anything
 *     not resolved to exactly one confident match is skipped and reported,
 *     never guessed.
 *
 * Report-only by default; --fix writes. See Usctdp_Void_Stale_Registrations
 * for the same report/--fix shape this follows.
 */
class Usctdp_Import_Legacy_Levels
{
    /** Levenshtein distance allowed when an exact normalized-name match fails. */
    const LAST_NAME_FUZZ = 2;
    const FIRST_NAME_FUZZ = 1;

    public function run($file_path, $fix = false)
    {
        if (!file_exists($file_path)) {
            WP_CLI::error(sprintf('File not found: %s', $file_path));
            return;
        }

        $json_content = file_get_contents($file_path);
        if ($json_content === false) {
            WP_CLI::error(sprintf('Could not read file: %s', $file_path));
            return;
        }

        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            WP_CLI::error(sprintf('Error decoding JSON from file %s: %s', $file_path, json_last_error_msg()));
            return;
        }

        // Phase 1: derive each legacy child's most-recent usable level,
        // purely from the JSON - no DB access yet.
        $legacy_results = [];
        $unattributed_regs = 0;
        foreach ($data as $family) {
            [$resolved, $skipped] = $this->resolve_child_levels($family);
            $unattributed_regs += $skipped;
            if (!empty($resolved)) {
                $legacy_results[] = ['family' => $family, 'children' => $resolved];
            }
        }

        if (empty($legacy_results)) {
            WP_CLI::success('No legacy registrations with a usable level found.');
            return;
        }

        // Phase 2: load every real family/student once, indexed for
        // in-memory matching rather than one query per legacy record.
        $all_families = (new Usctdp_Mgmt_Family_Query(['number' => 0]))->items;
        $families_by_norm_last = [];
        foreach ($all_families as $family_row) {
            $key = $this->normalize_name($family_row->last);
            if ($key === '') {
                continue;
            }
            $families_by_norm_last[$key][] = $family_row;
        }

        $all_students = (new Usctdp_Mgmt_Student_Query(['number' => 0]))->items;
        $students_by_family_id = [];
        foreach ($all_students as $student_row) {
            $students_by_family_id[$student_row->family_id][] = $student_row;
        }

        // Phase 3: match each legacy child to a real student and decide
        // what would change.
        $stats = [
            'legacy_children_with_level' => 0,
            'family_no_match' => 0,
            'family_ambiguous' => 0,
            'student_no_match' => 0,
            'student_ambiguous' => 0,
            'already_current' => 0,
            'students_to_update' => 0,
            'registrations_to_update' => 0,
        ];
        $ops = [];
        $issues = [];

        foreach ($legacy_results as $entry) {
            $legacy_family = $entry['family'];
            $resolved_children = $entry['children'];
            $stats['legacy_children_with_level'] += count($resolved_children);

            $candidates = $this->find_candidate_families($legacy_family, $families_by_norm_last, $all_families);
            if (empty($candidates)) {
                $stats['family_no_match'] += count($resolved_children);
                $issues[] = sprintf(
                    '[no family match] legacy "%s" (last="%s") - %d student(s) with a resolved level not matched',
                    $legacy_family['id'] ?? '?',
                    $legacy_family['last'] ?? '',
                    count($resolved_children)
                );
                continue;
            }

            $matched_family = $this->pick_best_family($candidates, $resolved_children, $students_by_family_id);
            if ($matched_family === null) {
                $stats['family_ambiguous'] += count($resolved_children);
                $names = implode(', ', array_map(function ($f) {
                    return sprintf('#%d "%s"', $f->id, $f->title);
                }, $candidates));
                $issues[] = sprintf(
                    '[ambiguous family] legacy "%s" (last="%s") matched multiple real families: %s',
                    $legacy_family['id'] ?? '?',
                    $legacy_family['last'] ?? '',
                    $names
                );
                continue;
            }

            $students = $students_by_family_id[$matched_family->id] ?? [];
            foreach ($resolved_children as $child_name => $info) {
                $student = $this->match_student($child_name, $students);
                if ($student === null) {
                    $stats['student_no_match']++;
                    $issues[] = sprintf(
                        '[no student match] legacy "%s" child "%s" -> family #%d "%s" has no single matching student (would set level %s)',
                        $legacy_family['id'] ?? '?',
                        $child_name,
                        $matched_family->id,
                        $matched_family->title,
                        $info['level']
                    );
                    continue;
                }

                if ((string) $student->level === (string) $info['level']) {
                    $stats['already_current']++;
                    continue;
                }

                $registrations = (new Usctdp_Mgmt_Registration_Query(['student_id' => $student->id, 'number' => 0]))->items;
                $ops[] = [
                    'student' => $student,
                    'old_level' => $student->level,
                    'new_level' => $info['level'],
                    'legacy_family_id' => $legacy_family['id'] ?? '',
                    'legacy_child' => $child_name,
                    'registrations' => $registrations,
                ];
                $stats['students_to_update']++;
                $stats['registrations_to_update'] += count($registrations);
            }
        }

        $this->report($ops, $issues, $stats, $unattributed_regs, $fix);

        if (!$fix) {
            return;
        }

        $updated_students = 0;
        $updated_registrations = 0;
        $student_query = new Usctdp_Mgmt_Student_Query();
        $registration_query = new Usctdp_Mgmt_Registration_Query();
        foreach ($ops as $op) {
            if ($student_query->update_item($op['student']->id, ['level' => $op['new_level']])) {
                $updated_students++;
            }
            foreach ($op['registrations'] as $registration) {
                if ($registration_query->update_item($registration->id, [
                    'student_level' => $op['new_level'],
                    'modified_at' => current_time('mysql'),
                    'modified_by' => get_current_user_id(),
                ])) {
                    $updated_registrations++;
                }
            }
        }

        WP_CLI::log('');
        WP_CLI::success(sprintf(
            'Updated %d student(s) and %d registration(s).',
            $updated_students,
            $updated_registrations
        ));
    }

    private function report($ops, $issues, $stats, $unattributed_regs, $fix)
    {
        $prefix = $fix ? '' : '[DRY RUN] ';

        if (!empty($ops)) {
            WP_CLI::log(sprintf('%sStudents to update:', $prefix));
            foreach ($ops as $op) {
                $student = $op['student'];
                WP_CLI::log(sprintf(
                    '  student #%d "%s %s" (family #%d) | level "%s" -> "%s" | %d registration(s) | legacy family "%s" child "%s"',
                    $student->id,
                    $student->first,
                    $student->last,
                    $student->family_id,
                    $op['old_level'],
                    $op['new_level'],
                    count($op['registrations']),
                    $op['legacy_family_id'],
                    $op['legacy_child']
                ));
            }
            WP_CLI::log('');
        }

        if (!empty($issues)) {
            WP_CLI::log(sprintf('%sUnmatched / ambiguous (skipped, needs manual review):', $prefix));
            foreach ($issues as $issue) {
                WP_CLI::log('  ' . $issue);
            }
            WP_CLI::log('');
        }

        WP_CLI::log(sprintf(
            'Legacy children with a resolved level: %d | already current: %d | to update: %d student(s) / %d registration(s)',
            $stats['legacy_children_with_level'],
            $stats['already_current'],
            $stats['students_to_update'],
            $stats['registrations_to_update']
        ));
        WP_CLI::log(sprintf(
            'Skipped - no family match: %d | ambiguous family: %d | no student match: %d | ambiguous student: %d',
            $stats['family_no_match'],
            $stats['family_ambiguous'],
            $stats['student_no_match'],
            $stats['student_ambiguous']
        ));
        if ($unattributed_regs > 0) {
            WP_CLI::log(sprintf(
                '%d legacy registration row(s) could not be attributed to any child in their family and were ignored.',
                $unattributed_regs
            ));
        }

        if (!$fix && !empty($ops)) {
            WP_CLI::log('');
            WP_CLI::log('Re-run with --fix to apply these updates.');
        }
    }

    /**
     * Returns [child_name => ['level' => normalized string, 'txn_date' =>
     * DateTime, 'session' => string], ..., $unattributed_reg_count].
     */
    private function resolve_child_levels($family)
    {
        $children = $family['children'] ?? [];
        if (empty($children)) {
            return [[], 0];
        }

        $normalized_children = [];
        foreach ($children as $child) {
            $name = trim($child['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $normalized_children[$this->normalize_name($name)] = $name;
        }
        if (empty($normalized_children)) {
            return [[], 0];
        }

        $single_child_name = (count($children) === 1) ? trim($children[0]['name'] ?? '') : null;

        $regs_by_child = [];
        $unattributed = 0;
        foreach ($family['regs'] ?? [] as $reg) {
            $target = $single_child_name;
            if ($target === null) {
                $target = $this->attribute_reg_to_child($reg['name'] ?? '', $normalized_children);
            }
            if ($target === null || $target === '') {
                $unattributed++;
                continue;
            }
            $regs_by_child[$target][] = $reg;
        }

        $resolved = [];
        foreach ($regs_by_child as $child_name => $regs) {
            $best = null;
            foreach ($regs as $reg) {
                $level = $this->normalize_level($reg['level'] ?? '');
                if ($level === null) {
                    continue;
                }
                $txn_date = $this->parse_legacy_date($reg['txn_date'] ?? '');
                if ($txn_date === null) {
                    continue;
                }
                if ($best === null || $txn_date > $best['txn_date']) {
                    $best = ['level' => $level, 'txn_date' => $txn_date, 'session' => $reg['session'] ?? ''];
                }
            }
            if ($best !== null) {
                $resolved[$child_name] = $best;
            }
        }

        return [$resolved, $unattributed];
    }

    /**
     * regs[].name is often the child's name plus an appended annotation
     * ("Ethan-5wks.", "Andrew - 4wks.", "Micah -last 3") - stripping
     * everything from the first "-" onward and matching what's left
     * resolves the large majority of these. Genuine nicknames/misspellings
     * ("Bill" for "William", "Mickal" for "Micah") are left unattributed
     * rather than guessed.
     */
    private function attribute_reg_to_child($reg_name, $normalized_children)
    {
        $reg_name = trim($reg_name);
        if ($reg_name === '') {
            return null;
        }

        $stripped = trim(explode('-', $reg_name, 2)[0]);
        $norm = $this->normalize_name($stripped);
        if (isset($normalized_children[$norm])) {
            return $normalized_children[$norm];
        }

        $norm_full = $this->normalize_name($reg_name);
        if (isset($normalized_children[$norm_full])) {
            return $normalized_children[$norm_full];
        }

        return null;
    }

    /**
     * Level values are mostly clean half-point strings ("1", "3.5"), with a
     * handful of data-entry artifacts: float-precision noise ("2.9000001")
     * and a missing decimal point ("25", "45" meaning "2.5", "4.5"). Both
     * are corrected here; anything else non-numeric is left unresolved
     * rather than guessed at.
     */
    private function normalize_level($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '' || !preg_match('/^\d+(\.\d+)?$/', $raw)) {
            return null;
        }

        if (preg_match('/^\d{2}$/', $raw)) {
            $raw = substr($raw, 0, 1) . '.' . substr($raw, 1);
        }

        $value = round((float) $raw, 1);
        return (fmod($value, 1) == 0.0) ? (string) (int) $value : rtrim(rtrim(sprintf('%.1f', $value), '0'), '.');
    }

    private function parse_legacy_date($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $date = DateTime::createFromFormat('m/d/y H:i:s', $raw);
        return $date ?: null;
    }

    private function normalize_name($str)
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $str));
    }

    /**
     * Candidate real families for a legacy family, matched by normalized
     * last name. Tries both `family.last` and the name portion of
     * `family.id` (the two disagree on spelling in ~2% of legacy records -
     * see class doc comment), falling back to a small Levenshtein distance
     * only when neither produces an exact hit.
     */
    private function find_candidate_families($legacy_family, $families_by_norm_last, $all_families)
    {
        $keys = [];
        $keys[] = $this->normalize_name($legacy_family['last'] ?? '');
        $id_name_part = preg_replace('/\s+\S*\d\S*\s*$/', '', $legacy_family['id'] ?? '');
        $keys[] = $this->normalize_name($id_name_part);
        $keys = array_values(array_unique(array_filter($keys)));

        $candidates = [];
        foreach ($keys as $key) {
            foreach ($families_by_norm_last[$key] ?? [] as $family_row) {
                $candidates[$family_row->id] = $family_row;
            }
        }

        if (empty($candidates) && !empty($keys)) {
            foreach ($all_families as $family_row) {
                $norm = $this->normalize_name($family_row->last);
                if ($norm === '') {
                    continue;
                }
                foreach ($keys as $key) {
                    if ($key !== '' && levenshtein($key, $norm) <= self::LAST_NAME_FUZZ) {
                        $candidates[$family_row->id] = $family_row;
                        break;
                    }
                }
            }
        }

        return array_values($candidates);
    }

    /**
     * Disambiguates multiple last-name candidates by how many legacy child
     * first names overlap with that family's actual students. Returns null
     * (ambiguous - report, don't guess) unless exactly one candidate has a
     * strictly-highest overlap of at least one child.
     */
    private function pick_best_family($candidates, $resolved_children, $students_by_family_id)
    {
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $child_keys = array_map([$this, 'normalize_name'], array_keys($resolved_children));

        $scored = [];
        foreach ($candidates as $family_row) {
            $students = $students_by_family_id[$family_row->id] ?? [];
            $student_keys = array_map(function ($s) {
                return $this->normalize_name($s->first);
            }, $students);
            $overlap = count(array_intersect($child_keys, $student_keys));
            $scored[] = ['family' => $family_row, 'overlap' => $overlap];
        }

        usort($scored, function ($a, $b) {
            return $b['overlap'] <=> $a['overlap'];
        });

        $top_overlap = $scored[0]['overlap'];
        if ($top_overlap === 0) {
            return null;
        }
        if (isset($scored[1]) && $scored[1]['overlap'] === $top_overlap) {
            return null;
        }
        return $scored[0]['family'];
    }

    /**
     * Matches a legacy child's first name to exactly one student within an
     * already-matched family. Exact normalized match first; a single
     * Levenshtein-distance-1 candidate as a typo-tolerant fallback.
     * Anything else (zero or multiple candidates at either tier) returns
     * null rather than guessing.
     */
    private function match_student($child_name, $students)
    {
        $norm_child = $this->normalize_name($child_name);

        $exact = array_values(array_filter($students, function ($s) use ($norm_child) {
            return $this->normalize_name($s->first) === $norm_child;
        }));
        if (count($exact) === 1) {
            return $exact[0];
        }
        if (count($exact) > 1) {
            return null;
        }

        $fuzzy = array_values(array_filter($students, function ($s) use ($norm_child) {
            $norm_student = $this->normalize_name($s->first);
            return $norm_student !== '' && levenshtein($norm_child, $norm_student) <= self::FIRST_NAME_FUZZ;
        }));
        if (count($fuzzy) === 1) {
            return $fuzzy[0];
        }

        return null;
    }
}
