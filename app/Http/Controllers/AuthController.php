<?php

namespace App\Http\Controllers;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function register() {

        $validator = Validator::make(request()->all(),[
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => Rule::in(EnumRole::$types),
            'password' => 'required|string|min:6'
        ]);

        if($validator->failed()){
            return response()-> json($validator->errors(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } else {
            // save data in try catch
            try {
                $user = User::create([
                    'name' => request('name'),
                    'email' => request('email'),
                    'role' => request('role'),
                    'password' => Hash::make(request('password'))
                ]);

                // new user
                $data  = new User;
                $data->message = 'Success register';

                if($user) {
                    return response()->json([
                        'status' => 'success',
                        'code' => 200,
                        'message' => $data
                    ]);
                } else {
                    return response()->json([
                        'status' => 'failed',
                        'code' => 400,
                        'message' => 'failed register'
                    ]);
                }


            } catch(QueryException $e) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "Failed masuk tycatc"
                ]);
            }
        }

    }

    /**
     * Get a JWT token via given credentials.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws ValidationException
     */
    public function login(Request $request)
    {
        $rules = [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ];
        $this->validate($request, $rules);

        $credentials = $request->only('email', 'password');
        $token = $this->guard()->attempt($credentials);

        // get data user
        $user = User::where('email', $request->email)->first();

        // get data token
        $responseWithToken = $this->respondWithToken($token)->original;
        $newUser = new User;
        $newUser->user_id = $user->id;
        $newUser->role = $user->role;
        $newUser->data = $responseWithToken;

        if ($token) {
            $response = response()->json([
                'status' => 'success',
                'code' => 200,
                'data' => $newUser
            ]);
        } else {
            $response = response()->json([
                'status' => 'failed',
                'code' => 400,
                'message' => "Username or password is incorrect."
            ]);
        }

        return $response;
    }

    /**
     * Get the authenticated User
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json($this->guard()->user());
    }

    /**
     * Log the user out (Invalidate the token)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        $this->guard()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken($this->guard()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\Guard
     */
    public function guard()
    {
        return Auth::guard();
    }
}

class EnumRole
{
    const DEFAULT = 'user';
    const ADMIN = 'admin';
    const USER = 'user';

    public static $types = [self::DEFAULT, self::ADMIN, self::USER];
}
