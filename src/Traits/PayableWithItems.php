<?php

namespace Marshmallow\Payable\Traits;

use Illuminate\Database\Eloquent\Model;

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
        return $this->invoiceAddress->first_name;
    }

    public function getBillingFamilyName()
    {
        return $this->invoiceAddress->last_name;
    }

    public function getCustomerPhonenumber()
    {
        return $this->customer->phone_number;
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
            "{$this->invoiceAddress->address_line_1} {$this->invoiceAddress->address_line_2}"
        );
    }

    public function getBillingStreetAdditional()
    {
        return $this->invoiceAddress->address_line_3;
    }

    public function getBillingPostalCode()
    {
        return $this->invoiceAddress->postal_code;
    }

    public function getBillingCity()
    {
        return $this->invoiceAddress->city;
    }

    public function getBillingRegion()
    {
        return $this->invoiceAddress->state;
    }

    public function getBillingCountry()
    {
        return $this->invoiceAddress->country?->alpha2 ?? 'NL';
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
        return $this->shippingAddress->first_name;
    }

    public function getShippingFamilyName()
    {
        return $this->shippingAddress->last_name;
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
            "{$this->shippingAddress->address_line_1} {$this->shippingAddress->address_line_2}"
        );
    }

    public function getShippingStreetAdditional()
    {
        return $this->shippingAddress->address_line_3;
    }

    public function getShippingPostalCode()
    {
        return $this->shippingAddress->postal_code;
    }

    public function getShippingCity()
    {
        return $this->shippingAddress->city;
    }

    public function getShippingRegion()
    {
        return $this->shippingAddress->state;
    }

    public function getShippingCountry()
    {
        return $this->shippingAddress->country?->alpha2 ?? 'NL';
    }

    public abstract function items();
}
