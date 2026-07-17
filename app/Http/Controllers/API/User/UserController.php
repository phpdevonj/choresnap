<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\ProviderType;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Service;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\API\UserResource;
use App\Http\Resources\API\ServiceResource;
use Illuminate\Support\Facades\Password;
use App\Models\Booking;
use App\Models\Wallet;
use App\Models\HandymanRating;
use App\Models\ProviderTaxMapping;
use App\Http\Resources\API\HandymanRatingResource;
use App\Traits\NotificationTrait;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationEmail;
use App\Mail\OtpMail;
use App\Models\PaymentGateway;
use Stripe\Stripe;
use Stripe\Account;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

class UserController extends Controller {
    use NotificationTrait;

    public function registerOld(Request $request) {
        $input = $request->all();
        $email = $input['email'];
        $username = $input['username'];
        $password = $input['password'];
        $input['display_name'] = $input['first_name'] . " " . $input['last_name'];
        $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'user';
        $input['password'] = Hash::make($password);

        if (in_array($input['user_type'], ['handyman', 'provider'])) {
            $input['status'] = isset($input['status']) ? $input['status'] : 0;
        }
        $user = User::withTrashed()
            ->where('username', $username)
            ->where('email', $email)
            ->where('user_type', $input['user_type'])
            ->first();

        if ($user) {
            if ($user->deleted_at == null) {

                $message = trans('messages.login_form');
                $response = [
                    'message' => $message,
                ];
                return comman_custom_response($response);
            }
            $message = trans('messages.deactivate');
            $response = [
                'message' => $message,
                'Isdeactivate' => 1,
            ];
            return comman_custom_response($response);
        }
        // Create the user
        $user = User::create($input);

        if (in_array($user->user_type, ['user', 'provider', 'handyman'])) {
            $user->assignRole($input['user_type']);
            $id = $user->id;

            $otp = rand(1000, 9999);

            // Store the OTP in the database
            $user->otp = $otp;
            $user->save();

            // Send OTP email
            \Mail::to($user->email)->send(new OtpMail($otp));


            // $verificationLink = route('verify',['id' => $id]);
            // Mail::to($user->email)->send(new VerificationEmail($verificationLink));
            $message = 'OTP has been sent to your email. Please Check your inbox';
            // $response = [
            //     'message' => $message,
            //     'data' => $user
            // ];
            // return comman_custom_response($response);
        } else {
            $user->assignRole($input['user_type']);
            $message = trans('messages.save_form', ['form' => $input['user_type']]);
        }

        if ($user->user_type == 'provider' || $user->user_type == 'user') {
            $wallet = array(
                'title' => $user->display_name,
                'user_id' => $user->id,
                'amount' => 0
            );
            $result = Wallet::create($wallet);
        }
        if (!empty($input['loginfrom']) && $input['loginfrom'] === 'vue-app' && $user->user_type != 'user') {
            return comman_custom_response([
                'message' => $message,
                'data' => $user
            ]);
        }
        $user->api_token = $user->createToken('auth_token')->plainTextToken;

        $response = [
            'message' => $message,
            'data' => $user,
            // 'stripe_response'=> $stripeResponse
        ];
        unset($input['password']);

        return comman_custom_response($response);
    }


    public function register(Request $request) {
        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'username' => 'required|string',
            'password' => 'required|string|min:6',
            'user_type' => 'required|in:user,provider,handyman',
        ]);

        $input = $request->all();
        $input['display_name'] = $input['first_name'] . ' ' . $input['last_name'];
        $input['password'] = Hash::make($input['password']);
        // $input['status'] = in_array($input['user_type'], ['provider', 'handyman']) ? ($input['status'] ?? 0) : 1;
        $input['status'] = 1;
        $username = $input['username'];
        $email = $input['email'];
        $userType = $input['user_type'];

        // prevent if username already used with different email
        $usernameConflict = User::where('username', $username)
            ->where('email', '!=', $email)
            ->exists();

        if ($usernameConflict) {
            return comman_custom_response([
                'message' => trans('messages.username_not_available')
            ], 403);
        }

        // prevent if email already used with different username
        $emailConflict = User::where('email', $email)
            ->where('username', '!=', $username)
            ->exists();

        if ($emailConflict) {
            return comman_custom_response([
                'message' =>  trans('messages.email_used_with_different_username')
            ], 403);
        }

        // prevent if same triplet already exists
        $exists = User::withTrashed()
            ->where('username', $username)
            ->where('email', $email)
            ->where('user_type', $userType)
            ->first();

        if ($exists) {
            if ($exists->deleted_at === null) {
                return comman_custom_response([
                    'message' => trans('messages.login_form')
                ]);
            }

            return comman_custom_response([
                'message' => trans('messages.deactivate'),
                'Isdeactivate' => 1
            ]);
        }

        // All clear: create user
        try {
            $user = User::create($input);
        } catch (\Illuminate\Database\QueryException $e) {
            return comman_custom_response([
                'message' => trans('messages.user_exists_with_same_details')
            ], 403);
        }

        if ($userType == 'provider') {
            $providerType = ProviderType::where('name', 'LIKE', '%freelance%')->first();
            if ($providerType) {
                $user->providertype_id = $providerType->id;
            }
        }

        $user->assignRole($userType);

        // OTP
        $otp = rand(1000, 9999);
        $user->otp = $otp;
        $user->save();

        try {
            \Mail::to($user->email)->send(new OtpMail($otp));
        } catch (\Exception $e) {
            // Mail failure fallback
        }

        // Wallet
        if (in_array($userType, ['user', 'provider'])) {
            Wallet::create([
                'title' => $user->display_name,
                'user_id' => $user->id,
                'amount' => 0
            ]);
        }

        // Trigger registration notification/email (template type = 'resgister')
        try {
            $this->sendNotification([
                'activity_type' => 'resgister',
                'user_id'       => $user->id,
                'user_type'     => $user->user_type,
            ]);
        } catch (\Exception $e) {
            \Log::error($e);
        }

        if (!empty($input['loginfrom']) && $input['loginfrom'] === 'vue-app' && $user->user_type != 'user') {
            return comman_custom_response([
                'message' => $message,
                'data' => $user
            ]);
        }
        $user->api_token = $user->createToken('auth_token')->plainTextToken;

        return comman_custom_response([
            'message' => __('messages.opt_send_to_email'),
            'data' => $user
        ]);
    }


    protected function createStripeConnectAccount(User $user, string $country_code): array {
        $paymentGateway = PaymentGateway::where('type', 'stripe')->first();
        if (!$paymentGateway) {
            return [
                'status' => 'error',
                'message' => 'Stripe payment gateway not configured',
                'code' => 500
            ];
        }

        $val = $paymentGateway->is_test == '1'
            ? json_decode($paymentGateway->value, true)
            : json_decode($paymentGateway->live_value, true);

        if (empty($val['stripe_key'])) {
            return [
                'status' => 'error',
                'message' => 'Stripe API key not configured',
                'code' => 500
            ];
        }

        Stripe::setApiKey($val['stripe_key']);

        try {


            // Prepare account data
            $accountData = [
                'type' => 'express', // Express To enable connect account login link
                'country' => $country_code,
                'email' => $user->email,
                'business_type' => 'individual',
                'capabilities' => [
                    'transfers' => ['requested' => true],
                    'card_payments' => ['requested' => true]
                ],
//                'tos_acceptance' => [
//                    'date' => time(),
//                    'ip' => $_SERVER['REMOTE_ADDR'],
//                ],
                'metadata' => [
                    'user_id' => $user->id,
                    'platform' => config('app.name')
                ]
            ];

            // Canada-specific requirements
            if ($country_code === 'CA') {
//                $accountData['tos_acceptance']['service_agreement'] = 'recipient';
            }

            // US-specific requirements
            if ($country_code === 'US') {
                $accountData['business_profile'] = [
                    'mcc' => '7399', // Miscellaneous business services
                    'url' => config('app.url')
                ];
            }

            $account = \Stripe\Account::create($accountData);
            $user->stripe_account_id = $account->id;
            $user->save();

            // Store the initial Stripe details; the name/verification state get
            // refreshed once the provider completes onboarding.
            syncStripeAccountDetails($user, $account);

            // Create onboarding link
            $accountLink = \Stripe\AccountLink::create([
                'account' => $account->id,
                'refresh_url' => route('stripe.reauth', ['account_id' => $account->id]),
                'return_url' => route('stripe.return', ['account_id' => $account->id]),
                'type' => 'account_onboarding',
            ]);

            return [
                'status' => 'success',
                'stripe_onboarding_url' => $accountLink->url
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'status' => 'error',
                'message' => 'Stripe account creation failed: ' . $e->getMessage(),
                'code' => 406
            ];
        }
    }


    public function loginOld(Request $request) {

        $Isactivate = request('Isactivate');
        $user_type = request('login_type');
        if ($Isactivate == 1) {
            $user = User::where('email', $email)
                ->where('user_type', $user_type)
                ->first();

            if ($user) {
                $user->restore();
            } else {
                $message = trans('auth.failed');
                return comman_message_response($message, 406);
            }

        }

        if (Auth::attempt(['email' => request('email'), 'password' => request('password'), 'user_type' => request('login_type')])) {
            $user = Auth::user();

            // Check if email is verified
            if ($user->is_email_verified != 1) {
                Auth::logout();
                return comman_message_response('Please verify your email before logging in.', 403);
            }

            // Set FCM token
            $user->fcm_token = $request->input('fcm_token');
            if (request('loginfrom') === 'vue-app') {
                if ($user->user_type != 'user') {
                    $message = trans('auth.not_able_login');
                    return comman_message_response($message, 400);
                }
            }

            $otp = rand(1000, 9999);

            // Store the OTP in the database
            $user->otp = $otp;
            $user->save();

            // Send OTP email
            \Mail::to($user->email)->send(new OtpMail($otp));


            $user->save();

            $success = $user;
            $success['user_role'] = $user->getRoleNames();
            $success['api_token'] = $user->createToken('auth_token')->plainTextToken;
            $success['profile_image'] = getSingleMedia($user, 'profile_image', null);
            $is_verify_provider = false;

            if ($user->user_type == 'provider') {
                $is_verify_provider = verify_provider_document($user->id);
                $success['subscription'] = get_user_active_plan($user->id);

                if (is_any_plan_active($user->id) == 0 && $success['is_subscribe'] == 0) {
                    $success['subscription'] = user_last_plan($user->id);
                }
                $success['is_subscribe'] = is_subscribed_user($user->id);
                $success['provider_id'] = admin_id();

            }
            if ($user->user_type == 'provider' || $user->user_type == 'user') {
                $wallet = Wallet::where('user_id', $user->id)->first();
                if ($wallet == null) {
                    $wallet = array(
                        'title' => $user->display_name,
                        'user_id' => $user->id,
                        'amount' => 0
                    );
                    Wallet::create($wallet);
                }
            }
            $success['is_verify_provider'] = (int)$is_verify_provider;
            unset($success['media']);
            unset($user['roles']);

            return response()->json(['data' => $success], 200);
        } else {
            $message = trans('auth.failed');
            return comman_message_response($message, 406);
        }
    }

    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'login_type' => 'required|in:user,provider,handyman'
        ]);

        $email = request('email');
        $password = request('password');
        $user_type = request('login_type');
        $fcm_token = request('fcm_token');

        // 🔁 Restore soft-deleted user if necessary
        if (request('Isactivate') == 1) {
            $deletedUser = User::withTrashed()
                ->where('email', $email)
                ->where('user_type', $user_type)
                ->first();

            if ($deletedUser && $deletedUser->trashed()) {
                $deletedUser->restore();
            } else {
                return comman_message_response(trans('auth.failed'), 406);
            }
        }

        // Attempt login
        if (Auth::attempt(['email' => $email, 'password' => $password, 'user_type' => $user_type])) {
            $user = Auth::user();

            // Check if email is verified
            if ($user->is_email_verified != 1) {
                Auth::logout();

                // Generate and send OTP
                $otp = rand(1000, 9999);
                $user->otp = $otp;
                $user->save();

                try {
                    \Mail::to($user->email)->send(new OtpMail($otp));
                } catch (\Exception $e) {
                    // Mail failure fallback
                }

//                return comman_message_response(__('auth.email_not_verified'), 403);
                return response()->json([
                                'status' => false,
                                'code' => 'EMAIL_NOT_VERIFIED',
                                'message' => __('auth.email_not_verified')
                                    ], 405);
            }
        } else {
            // Check if this user exists under another role
            $existingOtherUser = User::where('email', $email)
                ->where('user_type', '!=', $user_type)
                ->first();

            if ($existingOtherUser && Hash::check($password, $existingOtherUser->password)) {
                // Block if email not verified
                if ($existingOtherUser->is_email_verified != 1) {
                    // Generate and send OTP
                    $otp = rand(1000, 9999);
                    $existingOtherUser->otp = $otp;
                    $existingOtherUser->save();

                    try {
                        \Mail::to($existingOtherUser->email)->send(new OtpMail($otp));
                    } catch (\Exception $e) {
                        // Mail failure fallback
                    }

//                    return comman_message_response(__('auth.email_not_verified'), 403);
                    return response()->json([
                                'status' => false,
                                'code' => 'EMAIL_NOT_VERIFIED',
                                'message' => __('auth.email_not_verified')
                                    ], 405);
                }


                //  Auto-register user with same email + username + different role
                $newUser = $existingOtherUser->replicate(); // copy all fields
                $newUser->user_type = $user_type;
                $newUser->fcm_token = $fcm_token;
                // $newUser->status = in_array($user_type, ['provider', 'handyman']) ? 0 : 1;
                $newUser->status = 1;
                $newUser->save();


                if ($user_type == 'provider') {
                    $providerType = ProviderType::where('name', 'LIKE', '%freelance%')->first();
                    if ($providerType) {
                        $newUser->providertype_id = $providerType->id;
                        $newUser->save();
                    }
                }


                $newUser->assignRole($user_type);

                // Wallet
                if (in_array($user_type, ['user', 'provider'])) {
                    Wallet::firstOrCreate([
                        'user_id' => $newUser->id
                    ], [
                        'title' => $newUser->display_name,
                        'amount' => 0
                    ]);
                }

                // OTP
                /*$otp = rand(1000, 9999);
                $newUser->otp = $otp;
                $newUser->save();

                try {
                    \Mail::to($newUser->email)->send(new OtpMail($otp));
                } catch (\Exception $e) {
                    // log mail failure
                }*/

                // Check if email is verified for new user
                if ($newUser->is_email_verified != 1) {
                    // Generate and send OTP
                    $otp = rand(1000, 9999);
                    $newUser->otp = $otp;
                    $newUser->save();

                    try {
                        \Mail::to($newUser->email)->send(new OtpMail($otp));
                    } catch (\Exception $e) {
                        // Mail failure fallback
                    }

//                    return comman_message_response(__('auth.email_not_verified'), 403);
                    return response()->json([
                                'status' => false,
                                'code' => 'EMAIL_NOT_VERIFIED',
                                'message' => __('auth.email_not_verified')
                                    ], 405);
                }

                // Token & response
                $newUser->api_token = $newUser->createToken('auth_token')->plainTextToken;
                unset($newUser['otp']);
                $response = $this->prepareLoginResponse($newUser);
                return response()->json(['data' => $response], 200);
            }

            //  Email doesn't exist or password wrong
            return comman_message_response(trans('auth.failed'), 406);
        }

        //  Existing user login successful
        $user->fcm_token = $fcm_token;
        $user->save();
        unset($user['otp']);

        // OTP
        /*$otp = rand(1000, 9999);
        $user->otp = $otp;
        $user->save();

        try {
            \Mail::to($user->email)->send(new OtpMail($otp));
        } catch (\Exception $e) {
            // mail failure fallback
        }*/

        $user->api_token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->prepareLoginResponse($user);
        return response()->json(['data' => $response], 200);
    }


    public function userList(Request $request) {
        $user_type = isset($request['user_type']) ? $request['user_type'] : 'handyman';
        $status = isset($request['status']) ? $request['status'] : 1;

        $user_list = User::orderBy('id', 'desc')->where('user_type', $user_type);
        if (!empty($status)) {
            $user_list = $user_list->where('status', $status);
        }

        if (default_earning_type() === 'subscription' && $user_type == 'provider' && auth()->user() !== null && !auth()->user()->hasRole('admin')) {
            $user_list = $user_list->where('is_subscribe', 1);
        }

        if (auth()->user() !== null && auth()->user()->hasRole('admin')) {
            $user_list = $user_list->withTrashed();
            if ($request->has('keyword') && isset($request->keyword)) {
                $user_list = $user_list->where('display_name', 'like', '%' . $request->keyword . '%');
            }
            if ($user_type == 'handyman' && $status == 0) {
                $user_list = $user_list->orWhere('provider_id', NULL)->where('user_type', 'handyman');
            }
            if ($user_type == 'handyman' && $status == 1) {
                $user_list = $user_list->whereNotNull('provider_id')->where('user_type', 'handyman');
            }

        }
        if ($request->has('provider_id')) {
            $user_list = $user_list->where('provider_id', $request->provider_id);
        }
        if ($request->has('city_id') && !empty($request->city_id)) {
            $user_list = $user_list->where('city_id', $request->city_id);
        }
        if ($request->has('keyword') && isset($request->keyword)) {
            $user_list = $user_list->where('display_name', 'like', '%' . $request->keyword . '%');
        }
        if ($request->has('booking_id')) {
            $booking_data = Booking::find($request->booking_id);

            $service_address = $booking_data->handymanByAddress;
            if ($service_address != null) {
                $user_list = $user_list->where('service_address_id', $service_address->id);
            }
        }
        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page === 'all') {
//                $per_page = $user_list->count();
                $per_page = min($user_list->count(), 200); // capped limit
            }
        }

        $user_list = $user_list->paginate($per_page);

        $items = UserResource::collection($user_list);

        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'next_page' => $items->nextPageUrl(),
                'previous_page' => $items->previousPageUrl(),
            ],
            'data' => $items,
        ];

        return comman_custom_response($response);
    }

    public function userDetail(Request $request) {
        $id = $request->id;

        $user = User::find($id);
        $message = __('messages.detail');
        if (empty($user)) {
            $message = __('messages.user_not_found');
            return comman_message_response($message, 400);
        }

        $service = [];
        $handyman_rating = [];
        $handyman = [];
        $profile_array = [];

        if ($user->user_type == 'provider') {
            $service = Service::where('provider_id', $id)->where('status', 1)->orderBy('id', 'desc')->paginate(10);
            $service = ServiceResource::collection($service);
            $handyman_rating = HandymanRating::where('handyman_id', $id)->orderBy('id', 'desc')->paginate(10);
            $handyman_rating = HandymanRatingResource::collection($handyman_rating);
            $handyman_staff = User::where('user_type', 'handyman')->where('provider_id', $id)->where('is_available', 1)->get();
            $handyman = UserResource::collection($handyman_staff);

            if (!empty($handyman_staff)) {
                foreach ($handyman_staff as $image) {
                    $profile_array[] = getSingleMedia($image, 'profile_image', null);

                    // $profile_array[] = $image->login_type !== null ? $image->social_image : getSingleMedia($image, 'profile_image',null);
                }
            }
        }
        $user_detail = new UserResource($user);
        if ($user->user_type == 'handyman') {
            $handyman_rating = HandymanRating::where('handyman_id', $id)->orderBy('id', 'desc')->paginate(10);
            $handyman_rating = HandymanRatingResource::collection($handyman_rating);
        }

        $response = [
            'data' => $user_detail,
            'service' => $service,
            'handyman_rating_review' => $handyman_rating,
            'handyman_staff' => $handyman,
            'handyman_image' => $profile_array,
        ];
        return comman_custom_response($response);

    }

    public function changePassword(Request $request) {
        $user = User::where('id', \Auth::user()->id)->first();

        if ($user == "") {
            $message = __('messages.user_not_found');
            return comman_message_response($message, 406);
        }

        $hashedPassword = $user->password;

        $match = Hash::check($request->old_password, $hashedPassword);

        $same_exits = Hash::check($request->new_password, $hashedPassword);
        if ($match) {
            if ($same_exits) {
                $message = __('messages.old_new_pass_same');
                return comman_message_response($message, 406);
            }

            $user->fill([
                'password' => Hash::make($request->new_password)
            ])->save();

            $message = __('messages.password_change');
            return comman_message_response($message, 200);
        } else {
            $message = __('messages.valid_password');
            return comman_message_response($message);
        }
    }

    public function updateProfile(Request $request) {
        $user = \Auth::user();
        if ($request->has('id') && !empty($request->id)) {
            $user = User::where('id', $request->id)->first();
        }
        if ($user == null) {
            return comman_message_response(__('messages.no_record_found'), 400);
        }

        $data = $request->all();

        $why_choose_me = [

            'why_choose_me_title' => $request->why_choose_me_title,
            'why_choose_me_reason' => isset($request->why_choose_me_reason) && is_string($request->why_choose_me_reason)
                ? array_filter(json_decode($request->why_choose_me_reason), function ($value) {
                    return $value !== null;
                })
                : null,

        ];

        $data['why_choose_me'] = ($why_choose_me);


        if (isset($data['email']) && $data['email'] != $user->email) {
            $data['is_email_verified'] = 0;
        }


        $user->fill($data)->update();

        if (isset($request->profile_image) && $request->profile_image != null) {
            $user->clearMediaCollection('profile_image');
            $user->addMediaFromRequest('profile_image')->toMediaCollection('profile_image');
        }

        $user_data = User::find($user->id);

        $message = __('messages.updated');
        // $user_data['profile_image'] = $user->login_type !== null ? $user->social_image : getSingleMedia($user_data,'profile_image',null);
        $user_data['profile_image'] = getSingleMedia($user_data, 'profile_image', null);

        $user_data['user_role'] = $user->getRoleNames();

        unset($user_data['roles']);
        unset($user_data['media']);

        $response = [
            'data' => $user_data,
            'message' => $message
        ];
        return comman_custom_response($response);
    }

    /*public function logout(Request $request){
        $auth = Auth::user();

        if($request->is('api*')){

           if(!Auth::guard('sanctum')->check()) {
            return response()->json(['status' => false, 'message' => __('messages.user_not_logged_in')]);
           }

          $user = Auth::guard('sanctum')->user();

          $user->tokens()->delete();

        return comman_message_response('Logout successfully');

       }
         Auth::logout();

        return comman_message_response('Logout successfully');
    }*/

    public function logout(Request $request) {
        if (!Auth::guard('sanctum')->check()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.user_not_logged_in')
            ]);
        }

        $user = Auth::guard('sanctum')->user();

        // Optional: Allow logging out from specific device/token
        if ($request->has('device_id')) {
            $user->tokens()->where('name', $request->device_id)->delete();
        } else {
            // Delete all tokens (current behavior)
            $user->tokens()->delete();
        }

        return comman_message_response('Logout successfully');

        Auth::logout();
        return comman_message_response('Logout successfully');
    }


    public function forgotPassword(Request $request) {
        $request->validate([
            'email' => 'required|email'
        ]);

        $users = User::where('email', $request->email)->get();
        if ($users->isEmpty()) {
            return comman_message_response('User not found', 404);
        }

        // Generate a random 4-digit OTP
        $otp = rand(1000, 9999);

        // Store the OTP in all matched users with same email
        foreach ($users as $user) {
            $user->otp = $otp;
            $user->save();
        }

        // Send OTP email
        \Mail::to($user->email)->send(new OtpMail($otp));
        return comman_message_response(__('messages.opt_send_to_email'), 200);
    }

    public function socialLogin(Request $request) {
        $input = array_filter($request->all());


        if ($input['login_type'] === 'mobile') {
            $user_data = User::where('username', $input['username'])->where('login_type', 'mobile')->first();
        } else {
            $user_data = User::where('email', $input['email'])->first();

        }
        $user_exist = false;
        if ($user_data != null) {
            if (!isset($user_data->login_type) || $user_data->login_type == '') {
                if (in_array(strtolower($request->login_type), ['google', 'facebook'])) {
                    $message = __('validation.unique', ['attribute' => 'email']);
                } else {
                    $message = __('validation.unique', ['attribute' => 'username']);
                }
                return comman_message_response($message, 400);
            }
            $user_data->fcm_token = $input['fcm_token'];

            $user_data->update($input);

            $user_exist = true;

            $message = __('messages.login_success');
        } else {

            if (in_array(strtolower($request->login_type), ['google', 'facebook'])) {
                $key = 'email';
                $value = $request->email;
            } else {
                $key = 'username';
                $value = $request->username;
            }

            $trashed_user_data = User::where($key, $value)->whereNotNull('login_type')->withTrashed()->first();

            if ($trashed_user_data != null && $trashed_user_data->trashed()) {
                if (in_array(strtolower($request->login_type), ['google', 'facebook'])) {
                    $message = __('validation.unique', ['attribute' => 'email']);
                } else {
                    $message = __('validation.unique', ['attribute' => 'username']);
                }
                return comman_message_response($message, 400);
            }

            if ($request->login_type === 'mobile' && $user_data == null) {
                $otp_response = [
                    'status' => true,
                    'is_user_exist' => false
                ];
                return comman_custom_response($otp_response);
            }
            if ($request->login_type === 'mobile' && $user_data != null) {
                $otp_response = [
                    'status' => true,
                    'is_user_exist' => true
                ];
                return comman_custom_response($otp_response);
            }

            $password = !empty($input['accessToken']) ? $input['accessToken'] : $input['email'];

            $input['user_type'] = "user";
            $input['display_name'] = $input['first_name'] . " " . $input['last_name'];
            $input['password'] = Hash::make($password);
            $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'user';
            $input['fcm_token'] = $input['fcm_token'] ?? '';

            $user = User::create($input);

            $user->assignRole($input['user_type']);

            $user_data = User::where('id', $user->id)->first();
            $message = trans('messages.save_form', ['form' => $input['user_type']]);
        }

        $user_data['api_token'] = $user_data->createToken('auth_token')->plainTextToken;
        $user_data['profile_image'] = getSingleMedia($user_data, 'profile_image', null);

        // $user_data['profile_image'] = $user_data->login_type !== null ? $user_data->social_image : getSingleMedia($user_data,'profile_image',null);
        $response = [
            'status' => true,
            'message' => $message,
            'data' => $user_data,
            'user_exist' => $user_exist
        ];
        return comman_custom_response($response);
    }

    public function userStatusUpdate(Request $request) {
        $user_id = $request->id;
        $user = User::where('id', $user_id)->first();

        if ($user == "") {
            $message = __('messages.user_not_found');
            return comman_message_response($message, 400);
        }
        $user->status = $request->status;
        $user->save();

        $message = __('messages.update_form', ['form' => __('messages.status')]);
        $response = [
            'data' => new UserResource($user),
            'message' => $message
        ];
        return comman_custom_response($response);
    }

    public function contactUs(Request $request) {
        try {
            \Mail::send('contactus.contact_email',
                array(
                    'first_name' => $request->get('first_name'),
                    'last_name' => $request->get('last_name'),
                    'email' => $request->get('email'),
                    'subject' => $request->get('subject'),
                    'phone_no' => $request->get('phone_no'),
                    'user_message' => $request->get('user_message'),
                ), function ($message) use ($request) {
                    $message->from($request->email);
                    $message->to(env('MAIL_FROM_ADDRESS'));
                });
            $messagedata = __('messages.contact_us_greetings');
            return comman_message_response($messagedata);
        } catch (\Throwable $th) {
            $messagedata = __('messages.something_wrong');
            return comman_message_response($messagedata);
        }

    }

    public function handymanAvailable(Request $request) {
        $user_id = $request->id;
        $user = User::where('id', $user_id)->first();

        if ($user == "") {
            $message = __('messages.user_not_found');
            return comman_message_response($message, 400);
        }
        $user->is_available = $request->is_available;
        $user->save();

        $message = __('messages.update_form', ['form' => __('messages.status')]);
        $response = [
            'data' => new UserResource($user),
            'message' => $message
        ];
        return comman_custom_response($response);
    }

    public function handymanReviewsList(Request $request) {
        $id = $request->handyman_id;
        $handyman_rating_data = HandymanRating::where('handyman_id', $id);

        $per_page = config('constant.PER_PAGE_LIMIT');

        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page === 'all') {
                $per_page = $handyman_rating_data->count();
            }
        }

        $handyman_rating_data = $handyman_rating_data->orderBy('created_at', 'desc')->paginate($per_page);

        $items = HandymanRatingResource::collection($handyman_rating_data);
        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'next_page' => $items->nextPageUrl(),
                'previous_page' => $items->previousPageUrl(),
            ],
            'data' => $items,
        ];
        return comman_custom_response($response);
    }

    public function deleteUserAccount(Request $request) {
        $user_id = \Auth::user()->id;
        $user = User::where('id', $user_id)->first();
        if ($user == null) {
            $message = __('messages.user_not_found');
            __('messages.msg_fail_to_delete', ['item' => __('messages.user')]);
            return comman_message_response($message, 400);
        }
        $user->booking()->forceDelete();
        $user->payment()->forceDelete();
        $user->forceDelete();
        $message = __('messages.msg_deleted', ['name' => __('messages.user')]);
        return comman_message_response($message, 200);
    }

    public function deleteAccount(Request $request) {
        $user_id = \Auth::user()->id;
        $user = User::where('id', $user_id)->first();
        if ($user == null) {
            $message = __('messages.user_not_found');
            __('messages.msg_fail_to_delete', ['item' => __('messages.user')]);
            return comman_message_response($message, 400);
        }
        if ($user->user_type == 'provider') {
            if ($user->providerPendingBooking()->count() == 0) {
                $user->providerService()->forceDelete();
                $user->providerPendingBooking()->forceDelete();
                $provider_handyman = User::where('provider_id', $user_id)->get();
                if (count($provider_handyman) > 0) {
                    foreach ($provider_handyman as $key => $value) {
                        $value->provider_id = NULL;
                        $value->update();
                    }
                }
                $user->forceDelete();
            } else {
                $message = __('messages.pending_booking');
                return comman_message_response($message, 400);
            }
        } else {
            if ($user->handymanPendingBooking()->count() == 0) {
                $user->handymanPendingBooking()->forceDelete();
                $user->forceDelete();
            } else {
                $message = __('messages.pending_booking');
                return comman_message_response($message, 400);
            }
        }
        $message = __('messages.msg_deleted', ['name' => __('messages.user')]);
        return comman_message_response($message, 200);
    }

    public function addUser(UserRequest $request) {
        $input = $request->all();

        $password = $input['password'];
        $input['display_name'] = $input['first_name'] . " " . $input['last_name'];
        $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'user';
        $input['password'] = Hash::make($password);

        if ($input['user_type'] === 'provider') {
        }
        $user = User::create($input);
        $user->assignRole($input['user_type']);
        $input['api_token'] = $user->createToken('auth_token')->plainTextToken;

        unset($input['password']);
        $message = trans('messages.save_form', ['form' => $input['user_type']]);
        $user->api_token = $user->createToken('auth_token')->plainTextToken;
        $response = [
            'message' => $message,
            'data' => $user
        ];
        return comman_custom_response($response);
    }

    public function editUser(UserRequest $request) {
        if ($request->has('id') && !empty($request->id)) {
            $user = User::where('id', $request->id)->first();
        }
        if ($user == null) {
            return comman_message_response(__('messages.no_record_found'), 400);
        }

        $user->fill($request->all())->update();

        if (isset($request->profile_image) && $request->profile_image != null) {
            $user->clearMediaCollection('profile_image');
            $user->addMediaFromRequest('profile_image')->toMediaCollection('profile_image');
        }

        $user_data = User::find($user->id);

        $message = __('messages.updated');
        $user_data['profile_image'] = getSingleMedia($user_data, 'profile_image', null);
        $user_data['user_role'] = $user->getRoleNames();
        unset($user_data['roles']);
        unset($user_data['media']);
        $response = [
            'data' => $user_data,
            'message' => $message
        ];
        return comman_custom_response($response);
    }

    public function userWalletBalance(Request $request) {
        $user = Auth::user();
        $amount = 0;
        $wallet = Wallet::where('user_id', $user->id)->first();
        if ($wallet !== null) {
            $amount = $wallet->amount;
        }
        $response = [
            'balance' => $amount,
        ];
        return comman_custom_response($response);
    }


    // user email verify
    public function verify(Request $request) {
        $email = $request->email;
        $user = User::where('email', $email)->first();
        if ($user === null) {
            $message = 'User not registered. Please check your email or register.';
            $response = [
                'message' => $message,
            ];
            return comman_custom_response($response);
        }
        if ($user->is_email_verified == 0) {
            $verificationLink = route('verify', ['id' => $user->id]);
            $response_data = Mail::to($user->email)->send(new VerificationEmail($verificationLink));
            try {
                $response_data = \Mail::to($user->email)->send(new VerificationEmail($verificationLink));
            } catch (\Exception $e) {
                //echo $e->getMessage()
            }

            $message = 'Email Verification link has been sent to your email. Please Check your inbox';
            $response = [
                'message' => $message,
                'is_email_verified' => $user->is_email_verified,
            ];
            return comman_custom_response($response);

        } else {
            $message = 'Email already verified.';
            $response = [
                'message' => $message,
                'is_email_verified' => $user->is_email_verified,
            ];


            return comman_custom_response($response);
        }
    }

    public function validateOtp(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:4',
            'type' => 'required|in:forgot,register,login',
            'login_type' => 'required|in:user,provider'
        ]);

        $user = User::where('email', $request->email)->where('user_type', $request->login_type)->first();

        if (!$user) {
            $message = __('messages.user_not_found');
            return comman_message_response($message, 406);
        }
        // Validate OTP
        if ($user->otp !== $request->otp) {
            $message = __('messages.invalid_otp');
            return comman_message_response($message, 406);
        }

        $stripeResponse = array();
        //Stripe logic only for non-user
        if ($request->type === 'register' && in_array($user->user_type, ['provider', 'handyman'])) {
            $stripeResponse = $this->createStripeConnectAccount($user, $request->country_code);
            if ($stripeResponse['status'] !== 'success') {
                // Delete user if Stripe account creation fails
                // $user->delete();
                return comman_custom_response($stripeResponse);
            } else {
                $user->is_email_verified = 1;
                $user->save();
                $response = [
                    'message' => __('messages.otp_valid'),
                    'stripe_response' => $stripeResponse,
                    'api_token' => $user->createToken('auth_token')->plainTextToken

                ];
                return comman_message_response($response, 200);
            }
        }
        if ($request->type === 'login') {
            $success['api_token'] = $user->createToken('auth_token')->plainTextToken;
        }
        $user->is_email_verified = 1;
        $user->save();


        $success['message'] = __('messages.otp_valid');
        $success['api_token'] = $user->createToken('auth_token')->plainTextToken;

        return comman_message_response($success, 200);
    }

    // New API for resetting password after OTP validation
    public function resetPassword(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'new_password' => 'required|confirmed|min:6'
        ]);
        $users = User::where('email', $request->email)->get();
        if ($users->isEmpty()) {
            $message = __('messages.user_not_found');
            return comman_message_response($message, 406);
        }
        // Update password for all matched users
        foreach ($users as $user) {
            $user->fill([
                'password' => Hash::make($request->new_password)
            ])->save();
        }
        $message = __('messages.password_reset_success');
        return comman_message_response($message, 200);
    }

    public function checkUniqueUsername(Request $request) {
        $request->validate([
            'username' => 'required|string|min:5|max:15',
        ]);

        $username = $request->input('username');
        $userExists = User::where('username', $username)->exists();

        if ($userExists) {
            return comman_message_response('Username exists', 409);
        }

        return comman_message_response('Success', 200);
    }

    private function prepareLoginResponse($user) {
        $data = $user->toArray();
        $data['user_role'] = $user->getRoleNames();
        $data['api_token'] = $user->api_token ?? $user->createToken('auth_token')->plainTextToken;
        $data['profile_image'] = getSingleMedia($user, 'profile_image', null);
        $data['is_verify_provider'] = 0;

        if ($user->user_type === 'provider') {
            $data['subscription'] = get_user_active_plan($user->id);

            if (is_any_plan_active($user->id) == 0 && ($data['is_subscribe'] ?? 0) == 0) {
                $data['subscription'] = user_last_plan($user->id);
            }

            $data['is_subscribe'] = is_subscribed_user($user->id);
            $data['provider_id'] = admin_id();
            $data['is_verify_provider'] = (int)verify_provider_document($user->id);
        }

        if (in_array($user->user_type, ['user', 'provider'])) {
            Wallet::firstOrCreate([
                'user_id' => $user->id
            ], [
                'title' => $user->display_name,
                'amount' => 0
            ]);
        }

        unset($data['media'], $data['roles']);
        return $data;
    }


}
