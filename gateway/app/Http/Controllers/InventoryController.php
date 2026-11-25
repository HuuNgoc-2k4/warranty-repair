<?php

namespace App\Http\Controllers;

use App\Clients\ServiceGateway;
use App\Exceptions\ServiceException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * @param ServiceGateway $services
     */
    public function __construct(protected ServiceGateway $services)
    {
    }

    /**
     * @return View
     */
    public function index(): View
    {
        try {
            $parts = $this->services->parts();
        } catch (ServiceException $e) {
            $parts = [];
            session()->now('error', $e->getMessage());
        }

        return view('inventory.index', ['parts' => $parts]);
    }
}
