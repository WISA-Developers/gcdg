<?php

namespace Wisa\Gcdg;

use Wisa\Gcdg\Exceptions\CommonException;

class App {


    /**
     * API 결과를 출력하고 세션을 종료한다.
     *
     * @param array $date
     **/
    public function output(Array $data)
    {
        header('Content-type: application/json');
        exit(json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK
        ));
    }

    /**
     * 접속자의 현재 선택된 프로젝트 값을 반환한다.
     *
     * @return integer | boolean
     **/
    public function currentProjectIdx()
    {
        if (isset($_REQUEST['project_idx'])) return $_REQUEST['project_idx'];
        if (isset($_SESSION['current_project_idx'])) return $_SESSION['current_project_idx'];

        return false;
    }

    /**
     * 접속자의 현재 직원 번호를 반환한다
     *
     * @return boolean
     *
     * @throws ValidAPIKeyException
     **/
    public function currentStaffIdx()
    {
        if (isset($_REQUEST['token'])) {
            $data = $this->db
                ->table('api_key')
                ->select('staff_idx', 'disabled')
                ->where('apiKey', $_REQUEST['token'])
                ->where(function($qb) {
                    $qb->where('type', 'key')
                       ->orWhere(function($qb) {
                            $qb->where('type', 'token')->where('expire_date', '>', $qb->raw('now()'));
                         });
                })
                ->first();

            if ($data && $data->disabled == 'Y') {
                throw new CommonException('사용이 중지된 API키 입니다.', 9999);
            }

            if ($data) return $data->staff_idx;

            throw new CommonException('API키가 정확하지 않습니다.', 9999);
        }

        throw new CommonException('인증되지 않은 사용자입니다.', 9999);
    }

    /**
     * 현재 접속자가 지정된 프로젝트에 해당 권한이 있는지 체크한다
     *
     * @param  string $auth
     * @param  integer $project_idx
     *
     * @return boolean
     *
     * @throws DataNotFoundException
     **/
    public function projectPermission($auth, $project_idx = null)
    {
        $auth = explode(',', $auth);

        // 최고 관리자
        if ($this->isAdmin()) return true;

        $project = $this->db->table('project')->where('idx', $project_idx)->first();
        if (!$project) {
            throw new CommonException('프로젝트를 찾을 수 없습니다.');
        }

        // 프로젝트 관리자 및 멤버
        if (in_array('project_admin', $auth) || in_array('member', $auth)) {
            $search_role = [];
            if (in_array('project_admin', $auth)) array_push($search_role, 'admin');
            if (in_array('member', $auth)) array_push($search_role, 'member');
            if (in_array('developer', $auth)) array_push($search_role, 'developer');

            if ($this->db
                ->table('project_staff')
                ->where('project_idx', $project_idx)
                ->where('staff_idx', $this->currentStaffIdx())
                ->whereIn('role', $search_role)
                ->count() > 0
            ) return true;
        }

        return false;
    }

    /**
     * 현재 접속자가 시스템 관리자인지 반환한다
     *
     * @return boolean
     *
     **/
    public function isAdmin() {
        return ($this->db
            ->table('site_admin')
            ->where('staff_idx', $this->currentStaffIdx())
            ->where('level', '1')
            ->count() > 0);
    }

    /**
     * 날자 형식을 설정값에 따라 변환한다
     *
     * @param  \DateTime $date
     *
     * @return string
     *
     **/
    public function dateformat(\DateTime $date)
    {
        global $define;
        return $date->format($define->date_format);
    }

    /**
     * 파일 용량을 단위에 따라 출력한다.
     *
     * @param  integer $filesize
     *
     * @return string
     **/
    public function filesizeHuman(int $filesize)
    {
        $units = ['KB', 'MB', 'GB', 'TB'];
        foreach ($units as $step => $unit) {
            $_filesize = $filesize/1024;
            if ($_filesize < 1024) {
                return number_format($_filesize).$unit;
            }
            $filesize = $_filesize;
        }
        return number_format($filesize);
    }

    public function issueStatus(String $status)
    {
        global $define;
        return $define->issue_stat->{$status} ?? '--';
    }

    public function projectStatus(String $status)
    {
        global $define;
        return $define->project_stat->{$status} ?? '--';
    }

    public function makeUploadDirectory($hash)
    {
        global $config;
        return [$config->fileserver->url, date('Ym').'/'];
    }

    public function makeFilename($filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (in_array($ext, ['php', 'html', 'htm', 'js']) == true) {
            throw new CommonException('업로드 불가능한 파일 확장자입니다.');
        }
        return md5(microtime()).'.'.$ext;
    }

    public function createAPIKey(string $type, int $staff_idx, string $description = '')
    {
        global $define;

        if ($type == 'token' && isset($_REQUEST['token'])) {
            $data = $this->db
                ->table('api_key')
                ->where('apikey', $_REQUEST['token'])
                ->first();
            if ($data) {
                if (strtotime($data->expire_date) > time()) {
                    $this->db->table('api_key')->where('apikey', $_REQUEST['token'])->update([
                        'staff_idx' => $staff_idx,
                        'expire_date' => date('Y-m-d H:i:s', strtotime('+'.$define->token_lifetime))
                    ]);
                    return $data->apikey;
                } else {
                    $this->db->table('api_key')->where('type', 'token')->where('expire_date', '<', $this->db->raw('now()'))->delete();
                }
            }
        }

        $token = base64_encode(str_shuffle(microtime().rand(0, 9999)));

        $this->db
            ->table('api_key')
            ->insert([
                'staff_idx' => $staff_idx,
                'type' => $type,
                'apikey' => $token,
                'description' => $description,
                'expire_date' => date('Y-m-d H:i:s', strtotime('+'.$define->token_lifetime)),
                'registerd' => $this->db->raw('now()')
            ]);

        return $token;
    }
}