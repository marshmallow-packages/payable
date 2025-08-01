<?php

namespace Marshmallow\Payable\Traits;

trait PayableWithItems
{
    public function getConsumerDateOfBirth()
    {
        return null;
    }

    public function getBillingOrganizationName()
    {
        return null;
    }

    public function getBillingTitle()
    {
        return null;
    }

    public function getBillingGivenName()
    {
        return $this->invoiceAddress()->first()?->first_name;
    }

    public function getBillingFamilyName()
    {
        return $this->invoiceAddress()->first()?->last_name;
    }

    public function getCustomerPhonenumber()
    {
        return $this->customer()->first()?->phone_number;
    }

    public function getBillingEmailaddress()
    {
        return $this->getCustomerEmail();
    }

    public function getBillingPhonenumber()
    {
        return $this->getCustomerPhonenumber();
    }

    public function getBillingStreetAndNumber()
    {
        return trim(
            "{$this->invoiceAddress()->first()?->address_line_1} {$this->invoiceAddress()->first()?->address_line_2}"
        );
    }

    public function getBillingStreetAdditional()
    {
        return $this->invoiceAddress()->first()?->address_line_3;
    }

    public function getBillingPostalCode()
    {
        return $this->invoiceAddress()->first()?->postal_code;
    }

    public function getBillingCity()
    {
        return $this->invoiceAddress()->first()?->city;
    }

    public function getBillingRegion()
    {
        return $this->invoiceAddress()->first()?->state;
    }

    public function getBillingCountry()
    {
        return $this->invoiceAddress()->first()?->country?->alpha2 ?? 'NL';
    }

    public function getShippingOrganizationName()
    {
        return null;
    }

    public function getShippingTitle()
    {
        return null;
    }

    public function getShippingGivenName()
    {
        return $this->shippingAddress()->first()->first_name;
    }

    public function getShippingFamilyName()
    {
        return $this->shippingAddress()->first()->last_name;
    }

    public function getShippingEmailaddress()
    {
        return $this->getCustomerEmail();
    }

    public function getShippingPhonenumber()
    {
        return $this->getCustomerPhonenumber();
    }

    public function getShippingStreetAndNumber()
    {
        return trim(
            "{$this->shippingAddress()->first()?->address_line_1} {$this->shippingAddress()->first()?->address_line_2}"
        );
    }

    public function getShippingStreetAdditional()
    {
        return $this->shippingAddress()->first()?->address_line_3;
    }

    public function getShippingPostalCode()
    {
        return $this->shippingAddress()->first()?->postal_code;
    }

    public function getShippingCity()
    {
        return $this->shippingAddress()->first()?->city;
    }

    public function getShippingRegion()
    {
        return $this->shippingAddress()->first()?->state;
    }

    public function getShippingCountry()
    {
        return $this->shippingAddress()->first()?->country?->alpha2 ?? 'NL';
    }

    public abstract function items();
}
