<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;

class HealthController extends Controller
{
    public function ping(Request $request): void
    {
        $this->success([
            'ok' => true,
            'app' => env('APP_NAME', 'HSEQ Training'),
        ], 'API en línea');
    }
}
