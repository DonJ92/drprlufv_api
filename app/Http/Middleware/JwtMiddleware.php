<?php

namespace App\Http\Middleware;

use Closure;
use JWTAuth;
use Exception;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use App\Http\Controllers\Controller;

class JwtMiddleware extends BaseMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, ...$roles)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (Exception $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                return app(Controller::class)->respondTokenError('Token is Invalid.');
            }else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                return app(Controller::class)->respondTokenError('Token is Expired. Please restart app and try again.');
            }else{
                return app(Controller::class)->respondTokenError('Authorization Token not found');
            }
        }
        if ($user && empty($roles)) {
            return $next($request);
        }
        
        if ($user && in_array($user->role, $roles)) {
            return $next($request);
        }
        return app(Controller::class)->respondTokenError('You are unauthorized to access this resource');
    }
}