<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\ParsedURI;
use Wisa\Gcdg\Exceptions\CommonException;
use Zeuxisoo\Core\Validator;

class Site extends App {

    protected $db;
    protected $config;

    public function __construct($sql, $config)
    {
        $this->db = $sql;
        $this->config = $config;
    }

    public function keys(ParsedURI $parsed_uri)
    {
        $res = $this->db
            ->table('api_key')
            ->where('staff_idx', $this->currentStaffIdx())
            ->where('type', 'key')
            ->orderBy('registerd', 'desc')
            ->get();

        $this->output([
            'status' => 'success',
            'list' => $res,
            'is_admin' => $this->isAdmin()
        ]);
    }

    public function genarate(ParsedURI $parsed_uri)
    {
        $staff_idx = $this->currentStaffIdx();

        $req = [
            'description' => $parsed_uri->getParameter('description'),
            'disabled' => $parsed_uri->getParameter('disabled')
        ];
        $validator = Validator::factory($req);
        $validator->add('description', 'API 사용 용도를 입력해주세요.')->rule('required');
        $validator->add('disabled', '중지상태를 입력해주세요.')->rule('required');

        if ($validator->inValid()) {
            throw new CommonException($validator->firstError());
        }

        $token = $this->createAPIKey('key', $staff_idx, $req['description']);

        $this->db
            ->table('api_key')
            ->where('apikey', $token)
            ->update([
                'disabled' => ($req['disabled'] == 'true') ? 'Y' : 'N'
            ]);

        $this->output([
            'status' => 'success',
            'token' => $token
        ]);
    }

    public function remove(ParsedURI $parsed_uri)
    {
        $idx = $parsed_uri->getParameter('args2');
        $key = $this->db
            ->table('api_key')
            ->where('staff_idx', $this->currentStaffIdx())
            ->where('type', 'key')
            ->where('idx', $idx)
            ->first();
        if (!$key) {
            throw new CommonException('권한이 없거나 존재하지 않는 키입니다.');
        }

        $this->db
            ->table('api_key')
            ->where('staff_idx', $this->currentStaffIdx())
            ->where('type', 'key')
            ->where('idx', $key->idx)
            ->delete();

        $this->output([
            'status' => 'success',
            'token' => $key->apikey
        ]);
    }

    public function toggle(ParsedURI $parsed_uri)
    {
        $idx = $parsed_uri->getParameter('args2');
        $key = $this->db
            ->table('api_key')
            ->where('staff_idx', $this->currentStaffIdx())
            ->where('type', 'key')
            ->where('idx', $idx)
            ->first();
        if (!$key) {
            throw new CommonException('권한이 없거나 존재하지 않는 키입니다.');
        }

        $disabled = ($key->disabled == 'Y') ? 'N' : 'Y';
        $this->db
            ->table('api_key')
            ->where('idx', $key->idx)
            ->update(['disabled' => $disabled]);

        $this->output([
            'status' => 'success',
            'disabled' => $disabled
        ]);
    }

    public function admins(ParsedURI $parsed_uri)
    {
        if (!$this->isAdmin()) {
            throw new CommonException('접근 권한이 없습니다.');
        }

        $res = $this->db
            ->table('site_admin')
            ->orderBy('registerd', 'asc')
            ->get();

        $_staffs = (new Staff($this->db, $this->config))->snapshot();
        foreach ($res as $data) {
            $staff = $_staffs[$data->staff_idx];
            $data->name = $staff->name;
            $data->group_name = $staff->group_name;
        }

        $this->output([
            'status' => 'success',
            'list' => $res
        ]);
    }

    public function summary(ParsedURI $parsed_uri)
    {
        $staff_idx = $this->currentStaffIdx();

        $my_projects = $this->db->table('project_staff')->selectDistinct(['project_idx'])->where('staff_idx', $staff_idx)->count();
        $my_issues = $this->db->table('issue')->where('creater_idx', $staff_idx)->count();
        $my_comments = $this->db->table('issue_comment')->where('creater_idx', $staff_idx)->count();

        $this->output([
            'status' => 'success',
            'data' => [
                'my_projects' => number_format($my_projects),
                'my_issues' => number_format($my_issues),
                'my_comments' => number_format($my_comments)
            ]
        ]);
    }

    public function setAdmin(ParsedURI $parsed_uri)
    {
        if (!$this->isAdmin()) {
            throw new CommonException('접근 권한이 없습니다.');
        }

        $staff_idx = $parsed_uri->getParameter('staff_idx');
        if (!$staff_idx) {
            throw new CommonException('관리자로 등록할 사원 아이디를 입력해주세요.');
        }
        $staff_idx = explode(',', $staff_idx);
        if (!count($staff_idx)) {
            throw new CommonException('관리자로 등록할 사원 아이디를 입력해주세요.');
        }

        $success = 0;
        foreach ($staff_idx as $idx) {
            try {
                $r = $this->db
                    ->table('site_admin')
                    ->insert([
                        'staff_idx' => $idx,
                        'level' => '1',
                        'registerd' => $this->db->raw('now()')
                    ]);
                if ($r) $success++;
            } catch(\Pecee\Pixie\Exceptions\DuplicateEntryException) {
                //
            }
        }

        if ($success == 0) {
            $this->output([
                'status' => 'error',
                'message' => '등록된 관리자가 없습니다.'
            ]);
        }

        $this->output([
           'status' => 'success',
           'message' => $success.'명의 관리자가 등록되었습니다.'
        ]);
    }

    public function adminRemove(ParsedURI $parsed_uri) {
        $idx = $parsed_uri->getParameter('args2');

        if (!$this->isAdmin()) {
            throw new CommonException('최고 관리자만 접근 가능합니다.');
        }
        
        $total_admins = $this->db->table('site_admin')->where('level', 1)->count();
        if ($total_admins < 2) {
            throw new CommonException('최소한 한 명의 최고관리자가 필요합니다.');
        }

        $data = $this->db->table('site_admin')->where('idx', $idx)->where('level', 1)->first();
        if (!$data) {
            throw new CommonException('등록되지 않은 관리자입니다.');
        }
        if ($data->staff_idx == $this->currentStaffIdx()) {
            throw new CommonException('자기 자신을 최고관리자에서 삭제할 수 없습니다.');
        }

        $this->db->table('site_admin')->where('idx', $idx)->delete();

        $this->output([
            'status' => 'success',
            'message' => '최고 관리자가 삭제되었습니다.'
        ]);
    }
}