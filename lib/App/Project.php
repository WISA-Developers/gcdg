<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\App\Staff;
use Wisa\Gcdg\App\Issue;
use Wisa\Gcdg\App\File;
use Wisa\Gcdg\ParsedURI;
use Wisa\Gcdg\Exceptions\CommonException;
use JasonGrimes\Paginator;
use Zeuxisoo\Core\Validator;

class Project extends App {

    protected $db;
    protected $config;

    public function __construct($sql, $config)
    {
        $this->db = $sql;
        $this->config = $config;
    }

    public function select(ParsedURI $parsed_uri)
    {
        $project_idx = (int) $parsed_uri->getParameter('args2');
        if ($this->projectPermission('admin,project_main,member,developer', $project_idx)) {
            $_SESSION['current_project_idx'] = $project_idx;

            $this->output([
                'status' => 'success',
                'project_idx' => $project_idx
            ]);
        }
        $this->output([
            'status' => 'faild',
            'message' => '프로젝트 접근 권한이 없습니다.'
        ]);
    }

    public function index(ParsedURI $parsed_uri)
    {
        $this->currentStaffIdx();

        $page = (int) $parsed_uri->getParameter('page', 1);
        $limit = (int) $parsed_uri->getParameter('limit', 15);

        $qry = $this->db
            ->table(['project' => 'p'])
            ->select([
                'p.*',
                $this->db->raw('group_concat(distinct(if(s.role="admin",s.staff_idx,null))) as admin_all'),
                $this->db->raw('group_concat(distinct(if(s.role="member",s.staff_idx,null))) as staffs_all')
            ])
            ->leftjoin(['project_staff', 's'], 's.project_idx', '=', 'p.idx')
            ->groupBy('p.idx');

        // 검색
        $title = $parsed_uri->getParameter('title');
        if ($title) $qry->where('p.project_name', 'like', '%'.$title.'%');
        $status = $parsed_uri->getParameter('status');
        if ($status) $qry->whereIn('p.status', explode(',', $status));
        $role = $parsed_uri->getParameter('role');
        if ($role) {
            $qry->join(['project_staff', 's2'], 'p.idx', '=', 's2.project_idx');
            $qry->whereIn('s2.staff_idx', is_array($role) ? $role : explode(',', $role));
        }
        $registerd_s = $parsed_uri->getParameter('registerd_s');
        $registerd_e = $parsed_uri->getParameter('registerd_e');
        if ($registerd_s && $registerd_e) {
            $qry->whereBetween('p.registerd', $registerd_s, $registerd_e.' 23:59:59');
        } else {
            if ($registerd_s) $qry->where('p.registerd', '>=', $registerd_s);
            if ($registerd_e) $qry->where('p.registerd', '<=', $registerd_e.' 23:59:59');
        }
        $modified_s = $parsed_uri->getParameter('modified_s');
        $modified_e = $parsed_uri->getParameter('modified_e');
        if ($modified_s && $modified_e) {
            $qry->whereBetween('p.modified', $modified_s, $modified_e.' 23:59:59');
        } else {
            if ($modified_s) $qry->where('p.modified', '>=', $modified_s);
            if ($modified_e) $qry->where('p.modified', '<=', $modified_e.' 23:59:59');
        }

        $paginator = new Paginator(
            $qry->count(),
            $limit,
            $page,
            "javascript:sethash({page:'(:num)'})"
        );
        $paginator->setMaxPagesToShow(10);
        $paginator->setPreviousText('');
        $paginator->setNextText('');

        // 스탭 정보 스냅샷
        $_staffs = (new Staff($this->db, $this->config))->snapshot();

        $res = $qry->orderBy('p.idx', 'desc')->limit($limit)->offset(($page-1)*$limit)->get();
        foreach ($res as $data) {
            $data->status = $this->projectStatus($data->status);
            $data->registerd = $this->dateformat(new \DateTime($data->registerd));

            $data->admin_all = ($data->admin_all) ? explode(',', $data->admin_all) : [];
            foreach ($data->admin_all as $_staff_idx) {
                $data->admin[] = $_staffs[$_staff_idx];
            }
            unset($data->admin_all);

            $data->staffs_all = ($data->staffs_all) ? explode(',', $data->staffs_all) : [];
            foreach ($data->staffs_all as $_staff_idx) {
                if (is_array($_staffs[$_staff_idx])) {
                    $data->staffs[] = $_staffs[$_staff_idx];
                }
            }
            unset($data->staffs_all);
        }

        $this->output([
            'status' => 'success',
            'data' => $res,
            'page' => $page,
            'paginator' => (string) $paginator
        ]);
    }

    public function get(ParsedURI $parsed_uri)
    {
        $idx = (int) $parsed_uri->getParameter('args2');

        if (!$this->projectPermission('admin,project_admin', $idx)) {
            throw new CommonException('프로젝트 조회 권한이 없습니다.');
        }

        if (!$idx) {
            $this->output([
                'status' => 'error',
                'new_hash' => md5(microtime()),
            ]);
        }

        $data = $this->db
            ->table('project')
            ->where('idx', $idx)
            ->first();

        if (!$data) {
            throw new CommonException('project is not found');
        }

        // 담당자 목록
        $all_staff_idx = []; // 프로젝트 내 담당자 전체 pkey
        $res = $this->db
            ->table('project_staff')
            ->select(['staff_idx', 'role'])
            ->where('project_idx', '=', $idx)
            ->get();

        // 프로젝트 내 담당자 전체 pkey 구하기
        foreach ($res as $staff) {
            array_push($all_staff_idx, $staff->staff_idx);
        }

        // 담당자 목록 생성
        $data->staffs_count = 0;
        $_staffs = Staff::all($all_staff_idx);
        foreach ($res as $staff) {
            if (isset($data->{$staff->role}) == false) $data->{$staff->role} = [];

            array_push(
                $data->{$staff->role},
                array_merge((array) $staff, (array) $_staffs[$staff->staff_idx])
            );
            if ($staff->role != 'creater' && $staff->role != 'referer') {
                $data->staffs_count++;
            }
        }

        $this->output([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function set(ParsedURI $parsed_uri)
    {
        $idx = (isset($_POST['idx'])) ? $_POST['idx'] : 0;

        // 권한 체크
        if (!$idx) {
            if (!$this->projectPermission('admin', $idx)) {
                throw new CommonException('프로젝트 등록 권한이 없습니다.');
            }
        } else {
            if (!$this->projectPermission('admin,project_admin', $idx)) {
                throw new CommonException('프로젝트 등록 권한이 없습니다.');
            }
        }

        $validator = Validator::factory($_POST);
        $validator->add('hash', '해시데이터가 존재하지 않습니다.')->rule('required');
        $validator->add('project_name', '프로젝트명을 입력해주세요.')->rule('required');
        $validator->add('status', '프로젝트 상태를 입력해주세요.')->rule('required');
        $validator->add('admin', '프로젝트 관리자를 한명 이상 선택해주세요.')->rule('required');

        if ($validator->inValid()) {
            throw new CommonException($validator->firstError());
        }

        $data = [
            'hash' => $_POST['hash'],
            'project_name' => $_POST['project_name'],
            'content' => $_POST['content'],
            'creater_idx' => $this->currentStaffIdx(),
            'status' => $_POST['status'],
            'repository' => $_POST['repository'] ?? '',
            'repository_type' => $_POST['repository_type'] ?? 'SVN',
            'repository_user' => $_POST['repository_user'],
            'repository_pw' => $_POST['repository_pw']
        ];

        if ($idx > 0) {
            $this->db
                ->table('project')
                ->where('idx', $idx)
                ->update($data);
        } else {
            $data['hash'] = $_POST['hash'];
            $data['registerd'] = $this->db->raw('now()');

            $idx = $this->db
                ->table('project')
                ->insert($data);
        }

        // 담당자 저장
        foreach(['admin', 'member', 'developer'] as $role) {
            $this->setProjectRole($role, $idx, $_POST[$role] ?? []);
        }

        // 사용/미사용 이미지 마킹
        (new File($this->db, $this->config))->activeByHTML($idx, $_POST['hash'], $_POST['content']);

        // git 저장소 clone
        if ($_POST['repository_type'] == 'GIT') {
            if ($_POST['repository'] && $_POST['repository_user'] && $_POST['repository_pw']) {
                $git = new GIT($_POST['repository_user'], $_POST['repository_pw'], $_POST['repository']);
                $git->clone(__RES_DIR__.'/repository/'.md5($_POST['repository']));
            }
        }

        $this->output([
            'status' => 'success',
            'issue_idx' => $idx
        ]);
    }

    private function setProjectRole(string $role, int $project_idx, Array $list)
    {
        $remove = $this->db
            ->table('project_staff')
            ->where('project_idx', '=', $project_idx)
            ->where('role', $role);

        if (is_array($list) && count($list) > 0) {
            $remove->whereNotIn('staff_idx', $list)->delete();

            foreach ($list as $staff_idx) {
                $data = [
                    'project_idx' => $project_idx,
                    'staff_idx' => $staff_idx,
                    'role' => $role,
                  'registerd' => $this->db->raw('now()')
                ];

                $this->db
                    ->table('project_staff')
                    ->onDuplicateKeyUpdate($data)
                    ->insert($data);
            }
        } else {
            $remove->delete();
        }

        return count($list);
    }

    public function my($parsed_uri)
    {
        $res = $this->db
            ->table(['project' => 'p'])
            ->select([
                'p.*',
                $this->db->raw('group_concat(distinct(if(s.role="admin",s.staff_idx,null))) as admin_all'),
                $this->db->raw('group_concat(distinct(if(s.role="member",s.staff_idx,null))) as staffs_all')
            ])
            ->leftjoin(['project_staff', 's'], 's.project_idx', '=', 'p.idx')
            ->where('s.staff_idx', $this->currentStaffIdx())
            ->whereNotIn('p.status', ['C', 'H'])
            ->groupBy('p.idx')
            ->orderBy('p.registerd', 'desc')
            ->get();

        $this->output([
            'status' => 'success',
            'data' => $res,
        ]);
    }

    public function remove(ParsedURI $parsed_uri)
    {
        $idx = (int) $parsed_uri->getParameter('args2');
        $data = $this->db->table('project')->where('idx', $idx)->first();

        if (!$data) {
            throw new CommonException('존재하지 않는 프로젝트입니다.');
        }

        if (!$this->isAdmin()) {
            throw new CommonException('관리자만 삭제할 수 있습니다.');
        }

        // 첨부파일 삭제
        $this->db->table('asset_files')->where('hash', $data->hash)->update(['active' => 'N']);

        // 본문 삭제
        $this->db->table('project')->where('idx', $idx)->delete();

        // 이슈 삭제
        $res = $this->db
            ->select('idx')
            ->table('issue')
            ->where('project_idx', $idx)
            ->get();
        foreach ($res as $data) {
            $issue->_remove($data->idx);
        }

        $this->output([
            'result' => 'success',
            'message' => '삭제되었습니다.'
        ]);
    }

}