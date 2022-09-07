<?php

namespace App\Http\Controllers;
use App\Models\Order;
use App\Exceptions\InvalidRequestException;
use Carbon\Carbon;
use Yansongda\Pay\Pay;
use Illuminate\Http\Request;
use App\Events\OrderPaid;

class PaymentController extends Controller
{
    public function payByAlipay(Order $order, Request $request)
    {
        // 判断订单是否属于当前用户
        $this->authorize('own', $order);
        // 订单已支付或者已关闭
        if ($order->paid_at || $order->closed) {
            throw new InvalidRequestException('订单状态不正确');
        }

        // 调用支付宝的网页支付
        return app('alipay')->web([
            'out_trade_no' => $order->no, // 订单编号，需保证在商户端不重复
            'total_amount' => $order->total_amount, // 订单金额，单位元，支持小数点后两位
            'subject'      => '支付 Laravel Shop 的订单：'.$order->no, // 订单标题
        ]);
    }
    // 前端回调页面
    public function alipayReturn()
    {
        $config = config('pay');

            $data = Pay::alipay($config)->callback(); // 是的，验签就这么简单！

        return view('pages.success', ['msg' => '付款成功','订单号' => $data->out_trade_no]);
    }

    public function alipayNotify()
    {
        $config = config('pay');
        $alipay = Pay::alipay($config);
        try{
            $data = $alipay->callback(); // 是的，验签就这么简单！

            // $data->out_trade_no 拿到订单流水号，并在数据库中查询
            $order = Order::where('no', $data->out_trade_no)->first();
            // 正常来说不太可能出现支付了一笔不存在的订单，这个判断只是加强系统健壮性。
            if (!$order) {
                return 'fail';
            }
            // 如果这笔订单的状态已经是已支付
            if ($order->paid_at) {
                // 返回数据给支付宝
                return app('alipay')->success();
            }

            $order->update([
                'paid_at'        => Carbon::now(), // 支付时间
                'payment_method' => 'alipay', // 支付方式
                'payment_no'     => $data->trade_no, // 支付宝订单号
            ]);

            // 请自行对 trade_status 进行判断及其它逻辑进行判断，在支付宝的业务通知中，只有交易通知状态为 TRADE_SUCCESS 或 TRADE_FINISHED 时，支付宝才会认定为买家付款成功。
            // 1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号；
            // 2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额）；
            // 3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）；
            // 4、验证app_id是否为该商户本身。
            // 5、其它业务逻辑情况
        } catch (\Exception $e) {
            // $e->getMessage();
        }
        $this->afterPaid($order);
        return $alipay->success();
    }

}
