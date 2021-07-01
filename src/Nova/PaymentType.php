<?php

namespace Marshmallow\Payable\Nova;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Marshmallow\TagsField\Tags;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\BelongsTo;
use Marshmallow\Nova\TinyMCE\TinyMCE;
use Marshmallow\AdvancedImage\AdvancedImage;
use Marshmallow\Payable\Nova\PaymentProvider;
use Marshmallow\Payable\Models\PaymentType as PaymentTypeModel;

class PaymentType extends Resource
{
    /**
     * The position in the nova resource group
     *
     * @var integer
     */
    public static $priority = 20;

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = 'Marshmallow\Payable\Models\PaymentType';

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
        // return $this->provider->name;
    }

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Payment types');
    }

    /**
     * Get the text for the create resource button.
     *
     * @return string|null
     */
    public static function singularLabel()
    {
        return __('Payment type');
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'name', 'description', 'notice',
    ];

    public static $group = 'Payments';

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            BelongsTo::make(__('Provider'), 'provider', config('payable.nova.resources.payment_provider'))
                ->rules('required')
                ->required(),

            Text::make(__('Name'), 'name')->rules('required')->required(),
            Boolean::make(__('Active'), 'active')->help(
                __('Please note: if you disable this type, existing payments can still be processed but new payments can not be created.')
            ),

            Heading::make(__('Extra cost information')),
            Select::make(__('Cost type'), 'commission_type')->options([
                PaymentTypeModel::COMMISSION_PRICE => __('Extra fixed amount'),
                PaymentTypeModel::COMMISSION_PERCENTAGE => __('Extra percentage'),
            ]),

            Heading::make(__('Provider information')),
            Text::make(__('Type ID'), 'vendor_type_id'),
            Tags::make(__('Options'), 'vendor_type_options'),

            Heading::make(__('Rich content')),
            AdvancedImage::make(__('Icon'), 'icon')
                ->croppable(config('payable.rules.icon_rules')[0] / config('payable.rules.icon_rules')[1])
                ->resize(config('payable.rules.icon_rules')[0], config('payable.rules.icon_rules')[1]),
            TinyMCE::make(__('Description'), 'description'),
            TinyMCE::make(__('Notice'), 'notice'),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function actions(Request $request)
    {
        return [];
    }
}
