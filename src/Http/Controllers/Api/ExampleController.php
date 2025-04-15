<?php
/**
 *
 * This file is auto generate by Nicelizhi\Apps\Commands\Create
 * @author Steve
 * @date 2025-04-11 09:02:26
 * @link https://github.com/xxxl4
 *
 */
namespace NexaMerchant\Blog\Http\Controllers\Api;

use Illuminate\Foundation\Validation\ValidatesRequests;

class ExampleController extends Controller
{
    public function demo() {
        $data = [];
        $data['code'] = 300;
        $data['message'] = "succe1ss";
        return response()->json($data);
    }
}
