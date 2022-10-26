<?php

namespace Marshmallow\Payable\Nova;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\DateTime;

class Payment extends Resource
{
    public static $priority = 30;

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = 'Marshmallow\Payable\Models\Payment';

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    public static function label()
    {
        return __('Payments');
    }

    public static function singularLabel()
    {
        return __('Payment');
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        //
    ];

    public static $group = 'Payments';

    public static $group_icon = '<svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48"><path fill="var(--sidebar-icon)" d="M22 34h4v-2h2c1.1 0 2-.9 2-2v-6c0-1.1-.9-2-2-2h-6v-2h8v-4h-4v-2h-4v2h-2c-1.1 0-2 .9-2 2v6c0 1.1.9 2 2 2h6v2h-8v4h4v2zM40 8H8c-2.21 0-3.98 1.79-3.98 4L4 36c0 2.21 1.79 4 4 4h32c2.21 0 4-1.79 4-4V12c0-2.21-1.79-4-4-4zm0 28H8V12h32v24z"/></svg>';

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            MorphTo::make('Payments', 'payable')->types([
                config('payable.nova.resources.payment')
            ]),
            DateTime::make('Valid from'),
            DateTime::make('Valid till'),
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
