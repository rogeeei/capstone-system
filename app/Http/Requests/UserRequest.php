<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
 public function rules(): array
{
    $route = request()->route()->getName(); // ✅ Get the current route name

    if ($route === 'user.login') {
        return [
            'username' => 'required|string|max:255', // ✅ Removed unique constraint for login
            'password' => 'required|min:8',
        ];
    }

    if ($route === 'user.store') {
        return [
            'email'         => 'required|email|string|max:255|unique:users,email',
            'password'      => 'required|min:8|confirmed',
            'firstname'     => 'required|string|max:255',
            'middle_name'   => 'nullable|string|max:255',
            'lastname'      => 'required|string|max:255',
            'suffix'        => 'nullable|string|max:255',
            'phone_number'  => 'required|string|max:15',
            'brgy'          => 'required|string|max:255',
            'purok'         => 'nullable|string|max:255',
            'municipality'  => 'nullable|string|max:255',
            'province'      => 'nullable|string|max:255',
            'birthdate'     => 'nullable|date|date_format:Y-m-d',
            'role'          => 'required|in:admin,user',
            'image_path'    => 'nullable|string|max:255',
            'approved'      => 'nullable|boolean',
            'username'      => 'required|string|max:255|unique:users,username',
        ];
    }

    if ($route === 'user.password') {
        return [
            'password' => 'required|min:8|confirmed',
        ];
    }

    return []; // ✅ Return an empty array if the route doesn't match
}
}
