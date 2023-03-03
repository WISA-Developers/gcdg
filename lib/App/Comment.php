<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\App\Issue;
use Wisa\Gcdg\App\File;
use Wisa\Gcdg\ParsedURI;
use Wisa\Gcdg\Exceptions\CommonException;
use JasonGrimes\Paginator;
use Zeuxisoo\Core\Validator;

class Comment extends App {

    protected $db;
    protected $config;
    private   $issue;

    public function __construct($sql, $config)
    {
        $this->db = $sql;
        $this->config = $config;
        $this->issue = new Issue($this->db, $this->config);
    }

    public function list(ParsedURI $parsed_uri)
    {
        $issue_idx = $parsed_uri->getParameter('args2');
        if (!$issue_idx) {
            throw new CommonException('이슈티켓 번호를 입력해주세요.');
        }

        if (!$this->issue->issuePermission('read', $issue_idx)) {
            throw new CommonException('코멘트 조회 권한이 없습니다.');
        }

        $page = (int) $parsed_uri->getParameter('args3');
        $limit = (int) $parsed_uri->getParameter('limit');

        if (!$limit) $limit = 5;

        $qry = $this->db
            ->table('issue_comment')
            ->where('issue_idx', $issue_idx);
        $count = $qry->count();

        if (!$page) $page = 1;
        $paginator = new Paginator(
            $count,
            $limit,
            $page,
            "javascript:sethash({c:'(:num)', l:'c'})"
        );
        $paginator->setMaxPagesToShow(5);

        // 스탭 정보 스냅샷
        $_staffs = (new Staff($this->db, $this->config))->snapshot();

        $res = $qry
            ->select(['idx', 'creater_idx', 'content', 'registerd'])
            ->orderBy('idx', 'DESC')
            ->limit($limit)
            ->offset(($page-1)*$limit)
            ->get();
        foreach ($res as $data) {
            $data->creater = $_staffs[$data->creater_idx];
            $data->content = html_entity_decode($data->content);
            $data->registerd = $this->dateformat(new \DateTime($data->registerd));
        }

        $paginator->setMaxPagesToShow(10);
        $paginator->setPreviousText('');
        $paginator->setNextText('');

        $this->output([
            'status' => 'success',
            'data' => $res,
            'page' => $page,
            'paginator' => (string) $paginator,
            'count' => $count
        ]);
    }

    public function get(Mixed $parsed_uri)
    {
        $idx = $parsed_uri->getParameter('args2');
        if (!$idx) {
            throw new CommonException('코멘트 번호를 입력해주세요.');
        }

        $data = $this->db
            ->table(['issue_comment' => 'c'])
            ->select(['c.*', 'm.project_idx'])
            ->join(['issue', 'm'], 'm.idx', '=', 'c.issue_idx')
            ->where('c.idx', $idx)
            ->first();
        if (!$data) {
            throw new CommonException('존재하지 않는 코멘트번호입니다.');
        }

        if ($data->creater_idx != $this->currentStaffIdx()) {
            if ($this->issue->issuePermission('read', $data->issue_idx)) {
                throw new CommonException('코멘트 조회 권한이 없습니다.');
            }
        }

        $data->content = html_entity_decode($data->content);
        $data->registerd = $this->dateformat(new \DateTime($data->registerd));
        $data->modified = $this->dateformat(new \DateTime($data->modified));

        $this->output([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function set(ParsedURI $parsed_uri)
    {
        $idx = isset($_POST['idx']) ? (int) $_POST['idx'] : 0;

        $validator = Validator::factory($_POST);
        if (!$idx) {
            $validator->add('hash', '해시데이터가 존재하지 않습니다.')->rule('required');
            $validator->add('issue_idx', '이슈티켓 번호를 입력해주세요.')->rule('required');
        }
        $validator->add('content', '내용을 입력해주세요.')->rule('required');

        if ($validator->inValid()) {
            throw new CommonException($validator->firstError());
        }

        if ($idx) {
            $data = $this->db
                ->table(['issue_comment' => 'c'])
                ->select(['c.hash', 'c.creater_idx', 'm.project_idx'])
                ->join(['issue', 'm'], 'm.idx', '=', 'c.issue_idx')
                ->where('c.idx', $idx)
                ->first();
            if (!$data) {
                throw new CommonException('존재하지 않는 코멘트번호입니다.');
            }

            // 수정 권한
            if ($data->creater_idx != $this->currentStaffIdx()) {
                if (!$this->projectPermission('admin,project_admin', $data->project_idx)) {
                    throw new CommonException('코멘트 수정 권한이 없습니다.');
                }
            }

            $this->db
                ->table('issue_comment')
                ->where('idx', $idx)
                ->update(['content' => htmlentities($_POST['content'])]);
            $_POST['hash'] = $data->hash;
        } else {
            if (!$this->issue->issuePermission('read', $_POST['issue_idx'])) {
                throw new CommonException('코멘트 작성 권한이 없습니다.');
            }

            $idx = $this->db
                ->table('issue_comment')
                ->insert([
                    'issue_idx' => (int) $_POST['issue_idx'],
                    'hash' => $_POST['hash'],
                    'creater_idx' => $this->currentStaffIdx(),
                    'content' => htmlentities($_POST['content']),
                    'registerd' => $this->db->raw('now()'),
                    'modified' => $this->db->raw('now()')
                ]);

            // 담당자에게 알림 발송
            if ($_POST['push'] == 'true') {
                $staffs_idx = [];
                $staffs = $this->db
                    ->table('issue_staff')
                    ->selectDistinct(['staff_idx'])
                    ->where('issue_idx', $_POST['issue_idx'])
                    ->get();
                foreach ($staffs as $staff) {
                    array_push($staffs_idx, $staff->staff_idx);
                }
                if (count($staffs_idx) > 0) {
                    $issue = $this->db->select(['idx', 'title'])->table('issue')->where('idx', $_POST['issue_idx'])->first();
                    $this->weagleEye()->call('sendWisaHelper', [
                        'args1' => implode('@', $staffs_idx),
                        'args2' =>
                            "[Issue Tracker] 이슈에 코멘트가 작성되었습니다..\n\n".
                            "- {$issue->title}\n".
                            "--------------------------------------\n".
                            strip_tags($_POST['content']),
                        'args3' => 'cs',
                        'args4' => '@@type=link@@link_text=이슈 확인@@link_url='.__URL__.'/#/issue/view/'.$issue->idx,
                        'args7' => 'utf8'
                    ]);
                }
            }
        }

        // 사용/미사용 이미지 마킹
        $file = new File($this->db, $this->config);
        $file->activeByMarkDown($_POST['issue_idx'], $_POST['hash'], $_POST['content']);

        $this->output([
            'status' => 'success',
            'comment_idx' => $idx
        ]);
    }

    public function remove(ParsedURI $parsed_uri)
    {
        $idx = $parsed_uri->getParameter('args2');
        if (!$idx) {
            throw new CommonException('코멘트 번호를 입력해주세요.');
        }

        $data = $this->db
            ->table(['issue_comment' => 'c'])
            ->select(['c.idx', 'c.hash', 'c.creater_idx', 'm.project_idx'])
            ->join(['issue', 'm'], 'm.idx', '=', 'c.issue_idx')
            ->where('c.idx', $idx)
            ->first();
        if (!$data) {
            throw new CommonException('존재하지 않는 코멘트번호입니다.');
        }

        if ($data->creater_idx != $this->currentStaffIdx()) {
            if ($this->projectPermission('admin,project_admin', $data->project_idx)) {
                throw new CommonException('코멘트 삭제 권한이 없습니다.');
            }
        }

        $file = new File($this->db, $this->config);
        $file->toggleActive($data->hash, 'N');

        $this->db->table('issue_comment')->where('idx', $idx)->delete();

        $this->output([
            'status' => 'success',
            'message' => '삭제되었습니다.'
        ]);
    }

}