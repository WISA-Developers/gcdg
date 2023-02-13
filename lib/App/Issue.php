<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\App\Staff;
use Wisa\Gcdg\App\File;
use Wisa\Gcdg\ParsedURI;
use Wisa\Gcdg\Exceptions\CommonException;
use JasonGrimes\Paginator;
use Zeuxisoo\Core\Validator;

class Issue extends App {

    protected $db;
    protected $define;

    public function __construct($sql, $define)
    {
        $this->db = $sql;
        $this->config = $define;
    }

    public function issuePermission(String $type, Mixed $issue = null)
    {
        if ($type != 'read' && $type != 'write' && $type != 'remove') {
            throw new CommonException('요청 권한 타입에 오류가 있습니다.');
        }

        if (!is_object($issue)) {
            $issue = $this->db
                ->table('issue')
                ->select(['idx', 'project_idx', 'creater_idx', 'permission'])
                ->where('idx', $issue)
                ->first();
        }

        // 최고 관리자
        if ($this->isAdmin()) return true;

        // 프로젝트 관리자
        if ($this->db
            ->table('project_staff')
            ->where('project_idx', $issue->project_idx)
            ->where('staff_idx', $this->currentStaffIdx())
            ->where('role', 'admin')
            ->count() > 0
        ) return true;

        // 작성자
        if ($issue->creater_idx == $this->currentStaffIdx()) {
            return true;
        }

        if ($type == 'remove') return false;

        if ($type == 'read' || ($type == 'write' && $issue->permission == '0')) {
            // 프로젝트 담당자
            if ($this->db
                ->table('project_staff')
                ->where('project_idx', $issue->project_idx)
                ->where('staff_idx', $this->currentStaffIdx())
                ->count() > 0
            ) return true;
        } else if ($type == 'write') {
            switch($issue->permission) {
                case '1' : // 작성자
                    if ($issue->creater_idx == $this->currentStaffIdx()) return true;
                break;
                case '2' : // 담당자
                    $cnt = $this->db
                        ->table('issue_staff')
                        ->where('issue_idx', $issue->idx)
                        ->where('role', '!=', 'referer')
                        ->where('staff_idx', $this->currentStaffIdx())
                        ->count();
                    if ($cnt > 0) return true;
                break;
            }
        }

        return false;
    }


    public function index(ParsedURI $parsed_uri)
    {
        $project_idx = $this->currentProjectIdx();
        $page = (int) $parsed_uri->getParameter('page', 1);
        $limit = (int) $parsed_uri->getParameter('limit', 10);

        if (!$project_idx) {
            throw new CommonException('프로젝트를 선택해주세요');
        }

        $qry = $this->db
            ->table(['issue' => 'i'])
            ->select(['i.*', $this->db->raw('group_concat(distinct(s.staff_idx)) as staffs_all')])
            ->leftjoin(['issue_staff', 's'], 'i.idx', '=', 's.issue_idx')
            ->where('i.project_idx', $project_idx)
            ->where(function(object $qb) {
                $qb->where('s.role', '!=', 'referer');
                $qb->orWhereNull('s.role');
            })
            ->groupBy('i.idx');

        if (!$this->projectPermission('admin,project_admin,member,developer', $project_idx)) {
            throw new CommonException('프로젝트 접근권한이 없습니다.');
        }

        // 검색
        $title = $parsed_uri->getParameter('title');
        if ($title) $qry->where('i.title', 'like', '%'.$title.'%');
        $content = $parsed_uri->getParameter('content');
        if ($content) $qry->where('i.content', 'like', '%'.$content.'%');
        $importance = (int) $parsed_uri->getParameter('importance');
        if ($importance) $qry->where('i.importance', $importance);
        $except = $parsed_uri->getParameter('except');
        if ($except) $qry->whereNotIn('i.idx', explode(',', $except));
        $work_type = $parsed_uri->getParameter('work_type');
        if ($work_type) $qry->whereIn('i.work_type', explode(',', $work_type));
        $status = $parsed_uri->getParameter('status');
        if ($status) $qry->whereIn('i.status', explode(',', $status));
        $role = $parsed_uri->getParameter('role');
        if ($role) {
            $qry->join(['issue_staff', 's2'], 'i.idx', '=', 's2.issue_idx');
            $qry->whereIn('s2.staff_idx', is_array($role) ? $role : explode(',', $role));
        }
        $device = $parsed_uri->getParameter('device');
        if ($device) {
            $device = explode(',', $device);
            $qry->where(function(object $qb) use ($device) {
                foreach ($device as $_device) {
                    $qb->orWhere('i.device', 'like', '%'.$_device.'|%');
                }
            });
        }
        $plan_s = $parsed_uri->getParameter('plan_s');
        $plan_e = $parsed_uri->getParameter('plan_e');
        if ($plan_s && $plan_e) {
            $qry->where('i.plan_s', '<=', $plan_e);
            $qry->where('i.plan_e', '>=', $plan_s);
        } else {
            if ($plan_s) {
                $qry->where('i.plan_s', '<=', $plan_s);
                $qry->where('i.plan_e', '>=', $plan_s);
            }
            if ($plan_e) {
                $qry->where('i.plan_s', '>=', $plan_e);
                $qry->where('i.plan_e', '<=', $plan_e);
            }
        }
        $registerd_s = $parsed_uri->getParameter('registerd_s');
        $registerd_e = $parsed_uri->getParameter('registerd_e');
        if ($registerd_s && $registerd_e) {
            $qry->whereBetween('i.registerd', $registerd_s, $registerd_e.' 23:59:59');
        } else {
            if ($registerd_s) $qry->where('i.registerd', '>=', $registerd_s);
            if ($registerd_e) $qry->where('i.registerd', '<=', $registerd_e.' 23:59:59');
        }
        $modified_s = $parsed_uri->getParameter('modified_s');
        $modified_e = $parsed_uri->getParameter('modified_e');
        if ($modified_s && $modified_e) {
            $qry->whereBetween('i.modified', $modified_s, $modified_e.' 23:59:59');
        } else {
            if ($modified_s) $qry->where('i.modified', '>=', $modified_s);
            if ($modified_e) $qry->where('i.modified', '<=', $modified_e.' 23:59:59');
        }

        // 페이징
        $count = $qry->count();
        $paginator = new Paginator(
            $count,
            $limit,
            $page,
            "javascript:sethash({page:'(:num)'})"
        );
        $paginator->setMaxPagesToShow(10);
        $paginator->setPreviousText('');
        $paginator->setNextText('');

        // 스탭 정보 스냅샷
        $_staffs = (new Staff($this->db, $this->config))->snapshot();

        $res = $qry->orderBy('i.registerd', 'desc')->limit($limit)->offset(($page-1)*$limit)->get();
        foreach ($res as $data) {
            $data->registerd = $this->dateformat(new \DateTime($data->registerd));
            $data->modified = $this->dateformat(new \DateTime($data->modified));
            $data->status = [
                'code' => $data->status,
                'value' => $this->issueStatus($data->status)
            ];
            $data->device = ($data->device) ? explode('|', trim($data->device, '|')) : [];

            $data->staffs_all = ($data->staffs_all) ? explode(',', $data->staffs_all) : [];
            foreach ($data->staffs_all as $_staff_idx) {
                $data->staffs[] = $_staffs[$_staff_idx];
            }
            unset($data->staffs_all);

            $data->creater = $_staffs[$data->creater_idx];
        }

        $this->output([
            'status' => 'success',
            'data' => $res,
            'page' => $page,
            'paginator' => (string) $paginator,
            'count' => $count
        ]);
    }

    public function get(ParsedURI $parsed_uri)
    {
        $idx = (int) $parsed_uri->getParameter('args2');
        $scope = $parsed_uri->getParameter('scope');
        $scope =($scope) ? explode(',', $scope) : [];

        if (!$idx) {
            $this->output([
                'status' => 'error',
                'new_hash' => md5(microtime()),
            ]);
        }

        $data = $this->db
            ->table('issue')
            ->where('idx', '=', $idx)
            ->first();

        if (!$data) {
            throw new CommonException('이슈 데이터가 없습니다.');
        }

        if (!$this->issuePermission('read', $data)) {
            throw new CommonException('이슈 접근 권한이 없습니다.');
        }

        if ($data->plan_s == '0000-00-00') $data->plan_s = null;
        if ($data->plan_e == '0000-00-00') $data->plan_e = null;

        // 담당자 목록
        $all_staff_idx = []; // 이슈 내 담당자 전체 pkey
        $res = $this->db
            ->table('issue_staff')
            ->select(['idx' => 'role_idx', 'staff_idx', 'role'])
            ->where('issue_idx', '=', $idx)
            ->get();

        // 등록자 추가
        $res['creater'] = (object) ['staff_idx' => $data->creater_idx, 'role' => 'creater'];

        // 이슈 내 담당자 전체 pkey 구하기
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

        // 프로젝트 진행도
        if (in_array('plan', $scope)) {
            $total_days = 0;
            if ($data->plan_s != '0000-00-00' && $data->plan_e != '0000-00-00') {
                $total_days = strtotime($data->plan_e) - strtotime($data->plan_s);
                $total_days = ($total_days / 86400) + 1;
            }
            $data->plan = (object) [
                'total_days' => $total_days,
                'schedule' => []
            ];
            $res = $this->db
                ->table('issue_schedule')
                ->select('*')
                ->where('issue_idx', $data->idx)
                ->orderBy('schedule_date', 'asc')
                ->get();
            foreach ($res as $s) {
                if (!isset($data->plan->schedule[$s->schedule_date])) {
                    $data->plan->schedule[$s->schedule_date] = [];
                }
                if (!isset($data->plan->schedule[$s->schedule_date][$s->role_idx])) {
                    $data->plan->schedule[$s->schedule_date][$s->role_idx] = 0;
                }
                $data->plan->schedule[$s->schedule_date][$s->role_idx] += $s->schedule_type;
            }
        }

        // 관련 디바이스
        $data->device = ($data->device) ? explode('|', trim($data->device, '|')) : [];
        if ($data->repository) {
            $data->repository = json_decode($data->repository);
        }

        // 코드 한글 변환
        $data->parsed = [
            'project' =>
                $this->db
                    ->table('project')
                    ->select('project_name')
                    ->where('idx', '=', $data->project_idx)
                    ->setFetchMode(\PDO::FETCH_COLUMN)
                    ->first(),
            'status' => $this->issueStatus($data->status)
        ];

        $this->output([
            'status' => 'success',
            'data' => $data,
            'permission' => $this->issuePermission('write', $data)
        ]);

    }

    public function set(ParsedURI $parsed_uri)
    {
        $idx = (isset($_POST['idx'])) ? $_POST['idx'] : 0;

        $validator = Validator::factory($_POST);
        $validator->add('hash', '해시데이터가 존재하지 않습니다.')->rule('required');
        $validator->add('title', '제목을 입력해주세요.')->rule('required');
        $validator->add('work_type', '업무 종류를 선택해주세요.')->rule('required');
        $validator->add('status', '업무 상태를 선택해주세요.')->rule('required');
        $validator->add('permission', '수정 권한을 선택해주세요.')->rule('required');
        $validator->add('importance', '중요도를 입력해주세요.')->rule('required');
        $validator->add('importance', '중요도를 1~5 사이의 값으로 입력해 주세요.')->rule('match_pattern', '/^[0-5]$/');

        if ($validator->inValid()) {
            throw new CommonException($validator->firstError());
        }

        if (!$this->currentProjectIdx()) {
            throw new CommonException('프로젝트를 선택해주세요.');
        }

        $data = [
            'hash' => $_POST['hash'],
            'title' => $_POST['title'],
            'work_type' => $_POST['work_type'],
            'status' => $_POST['status'],
            'plan_s' => $_POST['plan_s'] ?? '',
            'plan_e' => $_POST['plan_e'] ?? '',
            'permission' => $_POST['permission'],
            'importance' => $_POST['importance'],
            'content' => $_POST['content'] ?? '',
            'modified' => $this->db->raw('now()'),
            'wep_idx' => $_POST['wep_idx'] ?? 0
        ];

        if (isset($_POST['device']) && is_array($_POST['device'])) {
            $data['device'] = implode('|', $_POST['device']).'|';
        }

        if (isset($_POST['repository']) && is_array($_POST['repository'])) {
            $tmp = [];
            foreach ($_POST['repository'] as $json) {
                array_push($tmp, json_decode($json));
            }
            $data['repository'] = json_encode($tmp);
        }

        if ($idx > 0) {
            $issue = $this->db->select(['idx', 'project_idx', 'creater_idx', 'permission'])->table('issue')->where('idx', $idx)->first();
            if (!$issue) {
                throw new CommonException('존재하지 않는 이슈입니다.');
            }
            $data['project_idx'] = $issue->project_idx;
            if ($issue->creater_idx != $this->currentStaffIdx()) {
                if (!$this->issuePermission('write', $issue)) {
                    throw new CommonException('수정 권한이 없습니다.');
                }

                // 수정 권한 변경은 작성자만 가능
                if ($issue->permission != $data['permission']) {
                    if (!$this->projectPermission('admin,project_admin', $issue->project_idx)) {
                        throw new CommonException('수정 권한은 작성자만 변경 가능합니다.');
                    }
                }
            }

            if ($this->db
                ->table('issue_schedule')
                ->where('issue_idx', $idx)
                ->where('schedule_date', '<', $_POST['plan_s'])
                ->count() > 0) {
                throw new CommonException('입력한 일정 이전에 등록된 스케쥴이 있습니다.');
            }

            if ($this->db
                    ->table('issue_schedule')
                    ->where('issue_idx', $idx)
                    ->where('schedule_date', '>', $_POST['plan_e'])
                    ->count() > 0) {
                throw new CommonException('입력한 일정 이후에 등록된 스케쥴이 있습니다.');
            }

            $this->db
                ->table('issue')
                ->where('idx', $idx)
                ->update($data);
        } else {
            if (!$this->projectPermission('admin,project_admin,member,developer', $this->currentProjectIdx())) {
                throw new CommonException('프로젝트 접근 권한이 없습니다.');
            }

            $data['registerd'] = $this->db->raw('now()');
            $data['project_idx'] = $this->currentProjectIdx();
            $data['creater_idx'] = $this->currentStaffIdx();

            $idx = $this->db
                ->table('issue')
                ->insert($data);
        }

        // 담당자 저장
        $new_staffs = [];
        foreach(['planner', 'designer', 'publisher', 'developer', 'tester', 'referer'] as $role) {
            $_new = $this->setRole($role, $idx, $data['project_idx'], $_POST[$role] ?? []);
            if (count($_new) > 0) {
                $new_staffs = array_merge($new_staffs, $_new);
            }
        }

        // 담당자 지정 알림 발송
        if (count($new_staffs) > 0) {
            $new_staffs = array_unique($new_staffs);

            $this->weagleEye()->call('sendWisaHelper', [
                'args1' => implode('@', $new_staffs),
                'args2' => "[Issue Tracker] 이슈에 멘션되었습니다.\n\n- {$data['title']}",
                'args3' => 'cs',
                'args4' => '@@type=link@@link_text=이슈 확인@@link_url='.__URL__.'/#/issue/view/'.$idx,
                'args7' => 'utf8'
            ]);
        }

        // 사용/미사용 이미지 마킹
        (new File($this->db, $this->config))->activeByMarkDown($idx, $data['hash'], $data['content']);

        $this->output([
            'status' => 'success',
            'issue_idx' => $idx
        ]);

    }

    private function setRole(String $role, int $issue_idx, int $project_idx, Array $list)
    {
        $remove = $this->db
            ->table('issue_staff')
            ->where('issue_idx', '=', $issue_idx)
            ->where('role', $role);

        $new_staffs = [];
        if (is_array($list) && count($list) > 0) {
            $remove->whereNotIn('staff_idx', $list)->delete();

            foreach ($list as $staff_idx) {
                $data = [
                    'project_idx' => $project_idx,
                    'issue_idx' => $issue_idx,
                    'staff_idx' => $staff_idx,
                    'role' => $role,
                    'registerd' => $this->db->raw('now()')
                ];

                $idx = $this->db
                    ->table('issue_staff')
                    ->onDuplicateKeyUpdate($data)
                    ->insert($data);
                if ($idx) {
                    array_push($new_staffs, $staff_idx);
                }
            }
        } else {
            $remove->delete();
        }

        return $new_staffs;
    }

    public function setStatus(ParsedURI $parsed_uri) {
        $request = [
            'idx' => $parsed_uri->getParameter('args2'),
            'status' => (string) $parsed_uri->getParameter('args3')
        ];

        $validator = Validator::factory($request);
        $validator->add('idx', 'issue 번호가 존재하지 않습니다.')->rule('required');
        $validator->add('status', '변경할 상태가 존재하지 않습니다.')->rule('required');

        if ($validator->inValid()) {
            throw new CommonException($validator->firstError());
        }

        // 권한 체크
        $issue = $this->db
            ->table('issue')
            ->where('idx', '=', $request['idx'])->first();
        if ($issue->creater_idx != $this->currentStaffIdx()) {
            if (!$this->issuePermission('write', $request['idx'])) {
                throw new CommonException('상태 변경 권한이 없습니다.');
            }
        }

        $this->db
            ->table('issue')
            ->where('idx', '=', $request['idx'])
            ->update($request);

        $this->output([
            'status' => 'success',
            'message' => '상태가 변경되었습니다.'
        ]);
    }

    public function setPlan(ParsedURI $parsed_uri)
    {
        $p = [
            'idx' => $parsed_uri->getParameter('args2'),
            'name' => (string)$parsed_uri->getParameter('n'),
            'value' => (string)$parsed_uri->getParameter('v'),
        ];

        $validator = Validator::factory($p);
        $validator->add('idx', 'issue 번호가 존재하지 않습니다.')->rule('required');
        $validator->add('name', '변경할 대상을 선택해주세요.')->rule('match_pattern', '/^plan_(s|e)$/');
        $validator->add('value', '변경할 날짜를 선택해주세요.')->rule('required');

        if ($validator->inValid()) {
            throw new CommonException($validator->firstError());
        }

        // 권한 체크
        $issue = $this->db
            ->table('issue')
            ->where('idx', '=', $p['idx'])->first();
        if ($issue->creater_idx != $this->currentStaffIdx()) {
            if (!$this->issuePermission('write', $p['idx'])) {
                throw new CommonException('상태 변경 권한이 없습니다.');
            }
        }

        if ($p['name'] == 'plan_e' && $issue->plan_s > $p['value']) {
            throw new CommonException('종료일은 시작일보다 이전으로 설정할 수 없습니다.');
        };

        $this->db
            ->table('issue')
            ->where('idx', '=', $p['idx'])
            ->update([
                $p['name'] => $p['value']
            ]);

        $this->output([
            'status' => 'success',
            'message' => '일정이 변경되었습니다.'
        ]);
    }

    public function setDayPlan(ParsedURI $parsed_uri)
    {
        $date = $parsed_uri->getParameter('date');
        $role_idx = (int) $parsed_uri->getParameter('role_idx');
        $schedule_type = (int) $parsed_uri->getParameter('schedule_type');

        $data = $this->db
            ->table('issue_schedule')
            ->select('idx')
            ->where('role_idx', $role_idx)
            ->where('schedule_type', $schedule_type)
            ->where('schedule_date', $date)
            ->first();

        // 등록 취소
        if ($data?->idx > 0) {
            $this->db
                ->table('issue_schedule')
                ->where('idx', $data->idx)
                ->delete();

            $this->output([
                'status' => 'success',
                'sch_idx' => $data->idx,
                'change_value' => -($schedule_type)
            ]);
        }

        // 등록
        $role = $this->db
            ->table('issue_staff')
            ->select('project_idx', 'issue_idx', 'staff_idx', 'role')
            ->where('idx', $role_idx)
            ->first();

        $request = [
            'project_idx' => $role->project_idx,
            'issue_idx' => $role->issue_idx,
            'role' => $role->role,
            'role_idx' => $role_idx,
            'creater_idx' => $this->currentStaffIdx(),
            'project_idx' => $role->project_idx,
            'schedule_type' => $schedule_type,
            'schedule_date' => $date,
            'registerd' => $this->db->raw('now()')
        ];

        $validator = Validator::factory($role);
        $validator->add('issue_idx', '이슈정보가 존재하지 않습니다.')->rule('required');
        $validator->add('role_idx', '담당자 정보가 존재하지 않습니다.')->rule('required');
        $validator->add('schedule_type', '예정(1)/실행(2) 여부를 입력해주세요.')->rule('required');

        $sch_idx = $this->db
            ->table('issue_schedule')
            ->insert($request);

        if ($sch_idx > 0) {
            $this->output([
                'status' => 'success',
                'sch_idx' => $sch_idx,
                'change_value' => $schedule_type
            ]);
        }
        throw new CommonException('스케쥴 등록 에러');
    }

    public function remove(ParsedURI $parsed_uri)
    {
        $idx = (int) $parsed_uri->getParameter('args2');
        $this->_remove($idx);
    }

    protected function _remove(int $idx) {
        $data = $this->db->table('issue')->where('idx', $idx)->first();

        if (!$data) {
            throw new CommonException('존재하지 않는 이슈입니다.');
        }

        if (!$this->issuePermission('remove', $data)) {
            throw new CommonException('삭제 권한이 없습니다.');
        }

        // 체인 삭제
        $this->db->table('issue_chain')->where('issue_idx', $idx)->orWhere('chain_idx', $idx)->delete();

        // 코멘트 삭제
        $res = $this->db->table('issue_comment')->where('issue_idx', $idx)->get();
        foreach ($res as $cmt) {
            $this->db->table('asset_files')->where('hash', $cmt->hash)->where('referer', 'comment')->update(['active' => 'N']);
        }
        $this->db->table('issue_comment')->where('issue_idx', $idx)->delete();

        // 산출물 및 첨부파일 삭제
        $this->db->table('asset_files')->where('hash', $data->hash)->update(['active' => 'N']);

        // 스케쥴 삭제
        $this->db->table('issue_schedule')->where('issue_idx', $idx)->delete();

        // 본문 삭제
        $this->db->table('issue')->where('idx', $idx)->delete();

        $this->output([
            'result' => 'success',
            'message' => '삭제되었습니다.'
        ]);
    }

    public function chain(ParsedURI $parsed_uri)
    {
        $idx = (int) $parsed_uri->getParameter('args2');
        $page = (int) $parsed_uri->getParameter('args3', 1);
        $limit = (int) $parsed_uri->getParameter('limit', 5);

        if (!$idx) {
            throw new CommonException('조회할 issue 번호를 입력해주세요.');
        }
        if (!$page) {
            $page = 1;
        }

        $qry = $this->db
            ->table(['issue_chain' => 'c'])
            ->join(['issue', 'i'], 'c.chain_idx', '=', 'i.idx')
            ->select('c.idx')
            ->where('c.issue_idx', $idx);
        $count = $qry->count();

        $paginator = new Paginator(
            $count,
            $limit,
            $page,
            "javascript:sethash({i:'(:num)', l:'i'})"
        );
        $paginator->setMaxPagesToShow(10);
        $paginator->setPreviousText('');
        $paginator->setNextText('');

        $res = $qry
            ->select(['c.idx' => 'chain_idx', 'i.*'])
            ->limit($limit)
            ->offset(($page-1)*$limit)
            ->orderBy('i.modified', 'desc')
            ->get();
        foreach ($res as $data) {
            $data->status = $this->issueStatus($data->status);
        }

        $this->output([
            'status' => 'success',
            'data' => $res,
            'page' => $page,
            'paginator' => (string) $paginator,
            'count' => $count
        ]);
    }

    public function addChain(ParsedURI $parsed_uri)
    {
        $issue_idx = $parsed_uri->getParameter('args2');
        $chain_idx = $parsed_uri->getParameter('chain_idx');
        $chain_idx = explode(',', trim($chain_idx, ','));

        if (!$issue_idx) {
            throw new CommonException('이슈 번호를 입력해주세요.');
        }
        if (!$chain_idx) {
            throw new CommonException('연결할 이슈 번호를 입력해주세요.');
        }

        $count = 0;
        foreach ($chain_idx as $_chain_idx) {
            $cnt = $this->db
                ->table('issue_chain')
                ->where('issue_idx', $issue_idx)
                ->where('chain_idx', $_chain_idx)
                ->count();
            if ($cnt > 0) continue;

            // 기본 등록
            $this->db
                ->table('issue_chain')
                ->insert([
                    'issue_idx' => $issue_idx,
                    'chain_idx' => $_chain_idx,
                    'creater_idx' => $this->currentStaffIdx(),
                    'registerd' => $this->db->raw('now()')
                ]);

            // 크로스 등록
            $this->db
                ->table('issue_chain')
                ->insert([
                    'issue_idx' => $_chain_idx,
                    'chain_idx' => $issue_idx,
                    'creater_idx' => $this->currentStaffIdx(),
                    'registerd' => $this->db->raw('now()')
                ]);

            $count++;
        }

        $this->output([
            'status' => 'success',
            'count' => $count
        ]);
    }

    public function removeChain(ParsedURI $parsed_uri)
    {
        $idx = (int) $parsed_uri->getParameter('args2');

        if (!$idx) {
            throw new CommonException('삭제할 이슈 체인 번호를 입력해주세요.');
        }
        $chain = $this->db
            ->table(['issue_chain'])
            ->select(['idx', 'chain_idx', 'issue_idx', 'creater_idx'])
            ->where('idx', $idx)
            ->first();
        if (!$chain) {
            throw new CommonException('이슈 체인 번호가 없습니다.');
        }

        if ($chain->creater_idx != $this->currentStaffIdx()) {
            if (!$this->issuePermission('write', $chain->issue_idx)) {
                throw new CommonException('이슈 수정 권한이 없습니다.');
            }
        }

        // 크로스 체인 삭제
        $this->db
            ->table('issue_chain')
            ->where('issue_idx', $chain->chain_idx)
            ->where('chain_idx', $chain->issue_idx)
            ->delete();

        $this->db
            ->table('issue_chain')
            ->where('idx', $chain->idx)
            ->delete();

        $this->output([
            'status' => 'success',
            'message' => '삭제되었습니다.'
        ]);
    }

}