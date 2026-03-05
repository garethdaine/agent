<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingPortalController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $returnUrl = config('billing.portal.return_url', '/dashboard');

        $return = filter_var($returnUrl, FILTER_VALIDATE_URL) ? $returnUrl : url($returnUrl);

        return $request->user()->redirectToBillingPortal($return);
    }
}
