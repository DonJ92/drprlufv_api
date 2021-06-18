<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function respondSuccess($data) {
        return $this->respond($data, 200);
    }

    public function respondValidateError($message) {
        return $this->respond(['message' => $message], 400);
    }

    public function respondNotFoundError($message) {
        return $this->respond(['message' => $message], 400);
    }

    public function respondTokenError($message) {
        return $this->respond(['message' => $message], 401);
    }

    public function respondServerError($message) {
        return $this->respond(['message' => $message], 500);
    }
      
    private function respond($data, $statusCode) {
        return response()->json($data, $statusCode);
    }
}
