<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\PhonePe;
use DB;

class PhonePeController extends Controller
{
    public $phonePeService;

    public function __construct(PhonePe $phonePeService)
    {
        $this->phonePeService = $phonePeService;
    }


    public function payment()
    {
        if ($order_id = session()->get('id')) {
            $total = 100;

            $requestData = [
                'amount' =>  $total * 100,
                'transactionId' => $transactionId,
                'merchantTransactionId' => $transactionId
            ];
            $response = $this->phonePeService->generateQrCode($requestData);
            if ($response['flag']) {
                return redirect($response['redirectUrl']);
            } else {
                request()->session()->flash('error', $response['message']);
                return redirect()->route('checkout');
            }
        } else {
            request()->session()->flash('error', "Something went wrong.");
            return redirect()->route('checkout');
        }
    }
    public function callback(Request $request)
    {
        // dd($request->all());
        if ($request->code === "PAYMENT_SUCCESS") {
            $order = Order::where('order_number', $request->transactionId)->first();
            if (!empty($order)) {
                $order->payment_status = "paid";
                $order->save();
                request()->session()->flash('success', 'You successfully pay from Phonepe! Thank You');
            }
        }
        session()->forget('cart');
        session()->forget('coupon');
        return redirect()->route('home');
    }

    public function test()
    {
        // $requestData = [
        //     'amount' => 1000,
        //     'transactionId' => 'mer_order_8',
        //     'merchantTransactionId' => 'mer_order_8'
        // ];
        $requestData = [
            'amount' => 100,
            'transactionId' => 'TX32321849644234',
            // 'merchantOrderId' => 'TX32321849644234'
            'merchantTransactionId' => 'TX32321849644234'
        ];

        $response = $this->phonePeService->generateQrCode($requestData);
        if ($response) {
            // echo "<img src='data:image/png;base64," . $response . "'>";
            echo $response;
            echo '<img src="https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($response) . '&chld=L|1&choe=UTF-8">';
            // echo "<a href='" . $response . "'>PAY</a>";
        }
    }
}
