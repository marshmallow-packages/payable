<?php

namespace Marshmallow\Payable\Nova;

use App\Nova\Resource;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Marshmallow\Payable\Payable;
use Laravel\Nova\Http\Requests\NovaRequest;
use Marshmallow\Payable\Models\PaymentProvider as PaymentProviderModel;

class PaymentProvider extends Resource
{
    /**
     * The position in the nova resource group
     *
     * @var integer
     */
    public static $priority = 10;

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = 'Marshmallow\Payable\Models\PaymentProvider';

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * Get the search result subtitle for the resource.
     *
     * @return string|null
     */
    public function subtitle()
    {
        return $this->type;
    }

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Providers');
    }

    /**
     * Get the text for the create resource button.
     *
     * @return string|null
     */
    public static function singularLabel()
    {
        return __('Provider');
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'name', 'type',
    ];

    public static $group = 'Payments';

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make(__('Name'), 'name')->required()->rules(['required'])->help(
                __('This name is for reference in the backoffice only. This will not be used on the website.')
            ),
            Select::make(__('Type'), 'type')->required()->rules(['required'])->options([
                PaymentProviderModel::PROVIDER_CUSTOM => 'Custom',
                PaymentProviderModel::PROVIDER_MOLLIE => 'Mollie',
                PaymentProviderModel::PROVIDER_MULTI_SAFE_PAY => 'Multi Safe Pay',
                Payable::IPPIES => 'ippies.nl',
            ]),
            // Boolean::make(__('Use simple checkout'), 'simple_checkout')->help(
            // __('Simple checkout means that you can just implement a "Pay now" button on you cart page. The type of payment (ideal, paypal, CC etc) will be done on the website of the payment provider')
            // ),
            Boolean::make(__('Active'), 'active')->help(
                __('Please note: if you disable this payment, existing payments can still be processed but new payments can not be created.')
            ),

            HasMany::make(__('Types'), 'types', config('payable.nova.resources.payment_type')),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
