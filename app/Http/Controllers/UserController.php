<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use Validator;
use JWTAuth;
use App\User;

class UserController extends Controller
{
    /**
     * Create a new UserController instance.
     *
     * @return void
     */
    public function __construct() {
        
    }

    /**
     * Display a listing of the user.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();

        $query = User::orderBy('name')->where('id', '!=' , $user->id);
        
        $query->where('role', '!=', config('constants.role.admin'));

        $rows = $query->get();

        return $this->respondSuccess($rows);        
    }

    /**
     * Store new user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(UserRequest $request ){
        // Password Validate
        $user = User::create(array_merge(
                    $request->validated(),
                    ['password' => bcrypt($request->password)]
                ));

		return $this->respondSuccess($user);
    }

    /**
     * Update existing user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id){
       
        $user = User::find($id);

        if (!$user) {
            return $this->respondNotFoundError('Could not find the user');
        }

        $validator = Validator::make($request->all(), [
            'email' => 'email|unique:users,email,'.$user->id,
            'name' => 'string',
            'role' => 'string',
            'prefer_work_hours' => 'numeric|min:0',
            'password' => 'nullable|string|min:6|max:10',
        ]);

        if ($validator->fails()) {
            return $this->respondValidateError($validator->errors()->first());
        }

        $params = $validator->validated();

        if ($request->has('password') && !empty($request->password)) {
            $params['password'] = bcrypt($request->password);
        } else {
            unset($params['password']);
        }

        $user->update($params);

        return $this->respondSuccess($user);
    }

    /**
     * Remove the specified user
     *
     * @return \Illuminate\Http\JsonResponse
     */
     public function delete($id) {
        $user = User::find($id);
        if (!$user) {
            return $this->respondNotFoundError('Could not find the user');
        }
        
        if ($user->delete()){ // physical delete
            return $this->respondSuccess(['message' => 'Successfull']);
        } else {
            return $this->response->respondServerError('Could not delete user');
        }
    }
}
