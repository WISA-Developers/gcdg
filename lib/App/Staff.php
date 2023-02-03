<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\ParsedURI;
use Wisa\Gcdg\Exceptions\CommonException;
use Pecee\Pixie\Connection as DBcon;

class Staff extends App {

    protected $db;
    protected $config;
    private $ep;

    public function __construct($db, $config)
    {
        $this->db = $db;
        $this->ep = self::epcon();
        $this->config = $config;
    }

    public static function epcon()
    {
        global $config;
        $db = new DBcon(
            'mysql',
            (Array) $config->ep->db
        );
        return $db->getQueryBuilder();
    }

    public function me(ParsedURI $parsedURI)
    {
        $staff_idx = $_SESSION['STAFF_IDX'] ?? null;
        $current_project_idx = $parsedURI->getParameter('current_project_idx', 0);

        if (!$staff_idx) {
            throw new CommonException('로그인해주세요.', 9999);
        }

        if (!isset($_REQUEST['token'])) {
            $_REQUEST['token'] = $this->createAPIKey('token', $staff_idx);
        }

        $staff = $this->ep
            ->table(['staff' => 's'])
            ->select(['s.idx', 's.name', 's.email', 'gi.name' => 'group_name'])
            ->join(['staff_group', 'g'], 's.idx', '=', 'g.staff_idx')
            ->join(['staff_group_info', 'gi'], 'g.staff_group_info_idx', '=', 'gi.idx')
            ->where('s.idx', $staff_idx)
            ->where('s.stat', '=', 2)
            ->where('s.partner_idx', '=', 1)
            ->where('s.out_stat', '=', 2)
            ->where('s.deleted', '=', 2)
            ->where('g.deleted', '=', 2)
            ->where('g.staff_group_type', '=', 2)
            ->where('gi.deleted', '=', 2)
            ->orderBy('s.name', 'asc')
            ->first();
        if (!$staff) {
            throw new CommonException('사원 로그인 정보에 오류가 있습니다.');
        }

        if ($current_project_idx > 0) {
            $current_project = $this->db
                ->table(['project' => 'p'])
                ->select(['p.idx', 'p.project_name', 'p.repository'])
                ->join(['project_staff', 's'], 'p.idx', '=', 's.project_idx')
                ->where('p.idx', $current_project_idx)
                ->where('s.staff_idx', $this->currentStaffIdx())
                ->first();
            if (!$current_project) {
                $current_project_idx = 0;
            }
        }
        if ($current_project_idx) {
            $_SESSION['current_project_idx'] = (int) $current_project_idx;
        }

        $this->output([
            'status' => 'success',
            'staff' => [
                'staff_idx' => $staff_idx,
                'name' => $staff->name,
                'group_name' => $staff->group_name,
                'portrait' => $this->getPortrait($staff_idx),
                'current_project_idx' => $current_project_idx,
                'current_project_name' => $current_project->project_name ?? '',
                'current_repository' => $current_project->repository ?? '',
                'admin' => ($this->db->table('site_admin')->where('staff_idx', $staff_idx)->count() == 1),
            ],
            'define' => $GLOBALS['define'],
            'staff_snapshot' => $this->snapshot(),
            'token' => $_REQUEST['token']
        ]);
    }

    public function list(ParsedURI $parsed_uri)
    {
        $res = [];
        $search_val = $parsed_uri->getParameter('search_str');
        if ($search_val) {
            $res = $this->ep
                ->table(['staff' => 's'])
                ->select(['s.idx', 's.name', 's.email', 'gi.name' => 'group_name'])
                ->join(['staff_group', 'g'], 's.idx', '=', 'g.staff_idx')
                ->join(['staff_group_info', 'gi'], 'g.staff_group_info_idx', '=', 'gi.idx')
                ->where('s.stat', '=', 2)
                ->where('s.partner_idx', '=', 1)
                ->where('s.out_stat', '=', 2)
                ->where('s.deleted', '=', 2)
                ->where('s.name', 'like', '%'.$search_val.'%')
                ->where('g.deleted', '=', 2)
                ->where('g.staff_group_type', '=', 2)
                ->where('gi.deleted', '=', 2)
                ->orderBy('s.name', 'asc')
                ->get();
        }

        $this->output([
            'status' => 'success',
            'data' => $res
        ]);
    }

    public static function all(?Array $staffs_idx = null)
    {
        $res = self::epcon()
            ->table(['staff' => 's'])
            ->select(['s.idx', 's.name', 's.email', 'gi.name' => 'group_name'])
            ->join(['staff_group', 'g'], 's.idx', '=', 'g.staff_idx')
            ->join(['staff_group_info', 'gi'], 'g.staff_group_info_idx', '=', 'gi.idx')
            ->where('s.stat', '=', 2)
            ->where('s.partner_idx', '=', 1)
            ->where('s.out_stat', '=', 2)
            ->where('s.deleted', '=', 2)
            ->where('g.deleted', '=', 2)
            ->where('g.staff_group_type', '=', 2)
            ->where('gi.deleted', '=', 2)
            ->orderBy('s.name', 'asc');
        if (is_array($staffs_idx) && count($staffs_idx) > 0) {
            $res->whereIn('s.idx', $staffs_idx);
        }

        $res = $res->get();

        $staffs = [];
        foreach ($res as $staff) {
            $staffs[$staff->idx] = $staff;
        }
        return $staffs;
    }

    protected function getPortrait(int $staff_idx)
    {
        $data = $this->ep
            ->table(['staff' => 's'])
            ->select(['f.name', 'f.type', 'f.reg_date'])
            ->join(['attach_file', 'f'], 'f.table_idx', '=', 's.idx')
            ->where('s.idx', '=', $staff_idx)
            ->where('s.deleted', '=', 2)
            ->where('f.deleted', '=', 2)
            ->where('f.tname', '=', 'staff')->first();

        if (!$data) {
            throw new CommonException('staff is not found');
        }

        return sprintf(
            '%s/_data/staff/s_dir/%s/%s',
            $this->config->ep->fileserver->url,
            date('Y/m/d', $data->reg_date),
            $data->name
        );
    }

    public function portrait(ParsedURI $parsed_uri)
    {
        $staff_idx = (int) $parsed_uri->getParameter('args2');

        header('Location: '.$this->getPortrait($staff_idx));
    }

    public function snapshot()
    {
        $snapshot = __INIT_DIR__.'/staff_snapshot.json';
        if (!file_exists($snapshot)) {
            $fp = fopen($snapshot, 'w');
        }
        if (!is_writable($snapshot)) {
            throw new CommonException('사원 정보 캐시 오류');
        }
        $snapshot_mtime = filemtime($snapshot);
        if (time()-$snapshot_mtime > 7200) {
            $staffs = self::all();
            foreach ($staffs as $staff) {
                $staff->portrait = $this->getPortrait($staff->idx);
            }
            $fp = fopen($snapshot, 'w');
            fwrite($fp, json_encode($staffs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
            fclose($fp);
        } else {
            $staffs = (array) json_decode(file_get_contents($snapshot));
        }

        return $staffs;
    }

    public function signout(ParsedURI $parsedURI)
    {
        $token = $parsedURI->getParameter('token');
        if ($token) {
            $this->db
                ->table('api_key')
                ->where('apikey', $token)
                ->where('staff_idx', $this->currentStaffIdx())
                ->delete();
        }

        unset($_SESSION['STAFF_IDX']);
        unset($_SESSION['current_project_idx']);


        $this->output([
            'status' => 'success',
            'message' => 'session disconnect'
        ]);
    }

}