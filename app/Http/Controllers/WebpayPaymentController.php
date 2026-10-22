<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\WebpayPaymentAttempt;
use App\Services\Payments\WebpayService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class WebpayPaymentController extends Controller
{
    public function start(Request $request, Order $order, WebpayService $webpay): View|RedirectResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        abort_unless($order->customerDetailsAreAvailable(), 404);

        try {
            return view('pages.webpay-redirect', $webpay->createPayment($order));
        } catch (RuntimeException $exception) {
            return back()->with('payment_error', $exception->getMessage());
        }
    }

    public function completed(Request $request): View|RedirectResponse
    {
        $attempt = $this->attemptFromRequest($request);

        if ($attempt && $request->user() && (int) $attempt->order?->user_id === (int) $request->user()->id) {
            $message = $attempt->fresh()->status === 'paid'
                ? 'Оплата WEBPAY подтверждена.'
                : 'WEBPAY обрабатывает платёж. Статус заказа обновится автоматически.';

            return redirect()->route('account.orders.show', $attempt->order_id)->with('payment_success', $message);
        }

        return view('pages.webpay-result', [
            'successful' => $attempt?->fresh()->status === 'paid',
            'message' => $attempt?->fresh()->status === 'paid'
                ? 'Оплата подтверждена.'
                : 'Платёж обрабатывается. Статус заказа обновится автоматически.',
        ]);
    }

    public function cancelled(Request $request): View|RedirectResponse
    {
        $attempt = $this->attemptFromRequest($request);

        if ($attempt && $attempt->status === 'pending') {
            $attempt->update(['status' => 'cancelled']);
        }

        if ($attempt && $request->user() && (int) $attempt->order?->user_id === (int) $request->user()->id) {
            return redirect()->route('account.orders.show', $attempt->order_id)
                ->with('payment_error', 'Оплата отменена. Вы можете повторить её позже.');
        }

        return view('pages.webpay-result', [
            'successful' => false,
            'message' => 'Оплата отменена. Вернитесь в личный кабинет, чтобы повторить её позже.',
        ]);
    }

    public function notify(Request $request, WebpayService $webpay)
    {
        try {
            $webpay->acceptNotification($request->all());

            return response('OK', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        } catch (ModelNotFoundException) {
            return response('Unknown order', 404);
        } catch (ConnectionException $exception) {
            report($exception);

            return response('Verification temporarily unavailable', 503);
        } catch (RuntimeException $exception) {
            report($exception);

            return response('Notification rejected', 422);
        } catch (Throwable $exception) {
            report($exception);

            return response('Temporary error', 503);
        }
    }

    private function attemptFromRequest(Request $request): ?WebpayPaymentAttempt
    {
        $number = (string) ($request->query('wsb_order_num') ?: $request->query('site_order_id'));

        return $number === ''
            ? null
            : WebpayPaymentAttempt::query()->with('order')->where('merchant_order_num', $number)->first();
    }
}
