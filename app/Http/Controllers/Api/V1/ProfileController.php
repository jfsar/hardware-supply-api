<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;

class ProfileController extends Controller
{
    /**
     * Update the authenticated customer's profile.
     */
    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): UserResource
    {
        return new UserResource($updateProfile($request->user(), $request->validated()));
    }
}
