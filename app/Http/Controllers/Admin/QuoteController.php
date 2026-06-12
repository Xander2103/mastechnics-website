<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;

class QuoteController extends Controller
{
    public function edit(CustomerRequest $customerRequest)
    {
        // TODO: implement quote edit view
        abort(501, 'Not implemented yet.');
    }

    public function store(CustomerRequest $customerRequest)
    {
        // TODO: implement quote store logic
        abort(501, 'Not implemented yet.');
    }

    public function performAction(CustomerRequest $customerRequest)
    {
        // TODO: implement quote action logic
        abort(501, 'Not implemented yet.');
    }
}
