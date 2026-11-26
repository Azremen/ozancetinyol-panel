@include('errors.page', ['code' => $exception->getStatusCode(), 'title' => __('errors.internal_server_error'), 'description' => __('errors.sorry_something_went_wrong_on_our_servers')])
