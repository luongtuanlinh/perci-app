<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Repositories\UserLoginRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends AdminController
{
    public $userRepository;
    public $userLoginRepository;
    public $type;

    public function __construct(UserRepository $userRepository,
                                UserLoginRepository $userLoginRepository)
    {
        $this->userRepository = $userRepository;
        $this->userLoginRepository = $userLoginRepository;
        $this->type = User::TYPE_USER;
    }

    public function all() {
        $all = $this->userRepository->allUser($this->type, ['name as text', 'id']);
        return $this->response(true, '', $all);
    }

    public function list(Request $request)
    {
        $list = $this->userRepository->userPaginate($this->type, $request->per_page);
        $data = [
            'data'  => $list->items(),
            'total' => $list->total()
        ];
        return $this->response(true, "", $data);
    }

    public function detail(Request $request) {
        $id = $request->id;
        $user = $this->userRepository->findById($id, ['name', 'phone', 'email', 'role']);
        return $this->response(true, '', $user);
    }

    public function store(UserRequest $request)
    {
        $data = $request->only('name', 'phone', 'email', 'role');
        $data['type'] = $this->type;
        DB::beginTransaction();
        try {
            $user_id = $this->userRepository->store($data);
            $this->userLoginRepository->storeUser(['user_id'=>$user_id, 'loginID'=>$data['email']], $this->type);
            DB::commit();
            return $this->response(true, 'thêm mới thành công');
        } catch(\Exception $ex) {
            DB::rollBack();
            Log::error('Create new user: fail. message: '.$ex->getMessage().'. file: '.$ex->getFile().'. line: '.$ex->getLine());
            return $this->response(false, 'thêm mới không thành công');
        }
    }

    public function update(UserRequest $request)
    {
        $data = $request->only('name', 'phone', 'email');
        DB::beginTransaction();
        try {
            $user_id = $this->userRepository->store($data, $request->id);
            $this->userLoginRepository->storeUser(['user_id'=>$user_id, 'loginID'=>$data['email']], $this->type);
            DB::commit();
            return $this->response(true, 'cập nhật dữ liệu thành công');
        } catch(\Exception $ex) {
            DB::rollBack();
            Log::error('update user: fail. message: '.$ex->getMessage().'. file: '.$ex->getFile().'. line: '.$ex->getLine());
            return $this->response(false, 'cập nhật không thành công');
        }
    }

    public function delete(Request $request)
    {
        $user = $this->userRepository->findById($request->id);

        if ( !$user ) {
            return $this->response(false, "dữ liệu không tồn tại hoặc đã bị xóa");
        }
        DB::beginTransaction();
        try{
            $user_login = $this->userLoginRepository->findUserLogin($user->id, $this->type);
            $user_login->delete();
            $user->delete();
            DB::commit();
            return $this->response(true, "xóa dữ liệu thành công");
        } catch(\Exception $ex) {
            Log::error('remove user: fail. message: '.$ex->getMessage().'. file: '.$ex->getFile().'. line: '.$ex->getLine());
            return $this->response(false, "xóa dữ liệu không thành công");
        }
    }
}
