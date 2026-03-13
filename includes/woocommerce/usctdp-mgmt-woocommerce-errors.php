<?php 

class Usctdp_Checkout_Exception extends Exception
{
    private string $slug;

    public function __construct($message, $slug, $code = 0, ?Throwable $previous = null)
    {
        $this->slug = $slug;
        parent::__construct($message, $code, $previous);
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}

class Usctdp_Woocommerce_Exception extends Exception
{
}
