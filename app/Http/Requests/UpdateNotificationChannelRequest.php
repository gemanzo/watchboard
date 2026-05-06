<?php

namespace App\Http\Requests;

class UpdateNotificationChannelRequest extends StoreNotificationChannelRequest
{
    // Identical validation rules; type can't be changed after creation,
    // but we still validate the incoming payload fully.
}
