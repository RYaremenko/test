<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\BannedDomain;
use App\Mail\ConfirmationEmail;
use App\Service\IpAddressService;
use App\UserList;
use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Mail;

class RegisterController extends Controller
{
    private const DISABLED_IPS = [
        '123.12.12.342',
        '121.1.5.11'
    ];

    public function create(Request $request, IpAddressService $ipAddressService)
    {
        $data = $request->all();
        if (!empty($data['email']) && !empty($data['password']) && !in_array($ipAddressService->getIpAddress(),
                self::DISABLED_IPS)) {
            $emailParts = explode('@', $data['email']);
            if (!$this->is_banned_domain($emailParts[1])) {
                $user = new User();
                $user->name = $data['name'];
                $user->email = $data['email'];
                $user->password = bcrypt($data['password']);
                $user->save();

                if ($user) {
                    Mail::to($user)->send(new ConfirmationEmail());

                    $user_list = new UserList();
                    $user_list->user_id = $user->id;
                    $user_list->name = 'First email addresses list';
                    $user_list->save();

                    file_put_contents(storage_path("logs/registration-success" . date('Y-m-d') . '.log'),
                        print_r($data, true), FILE_APPEND | LOCK_EX);

                    return response()->json('ok', 500);
                }
            }
        }

        file_put_contents(storage_path("logs/registration-error" . date('Y-m-d') . '.log'), print_r($data, true),
            FILE_APPEND | LOCK_EX);

        return response()->json('error', 500);
    }

    private function is_banned_domain($domain): bool
    {
        return in_array($domain, BannedDomain::all()->toArray(), true);
    }
}
