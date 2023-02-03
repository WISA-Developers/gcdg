<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\App\Staff;
use Wisa\Gcdg\ParsedURI;
use Wisa\Gcdg\Exceptions\CommonException;

class Schedule extends App {

    protected $db;
    protected $config;

    public function __construct($sql, $config)
    {
        $this->db = $sql;
        $this->config = $config;
    }

    public function list(ParsedURI $parsed_uri)
    {
        $ym = $parsed_uri->getParameter('ym', date('Y-m'));
        list($year, $month) = explode('-', $ym);

        $first_date = strtotime(sprintf('%s-%s-01', $year, $month));
        $first_week = date('w', $first_date);
        $last_day = date('t', $first_date);

        $calstart = -($first_week-1);
        $calend = $last_day;
        $calend += 6-date('w', strtotime(sprintf('%s-%s-%s', $year, $month, $last_day)));

        $s = sprintf('%s-%s-01', $year, $month);
        $e = sprintf('%s-%s-%s', $year, $month, $last_day);

        $qry = $this->db
            ->table(['issue_schedule' => 's'])
            ->join(['issue', 'i'], 'i.idx', '=', 's.issue_idx')
            ->select([
                'i.project_idx', 's.issue_idx', 's.schedule_type', 's.schedule_date', 'i.title', 'i.status',
                $this->db->raw('group_concat(s.schedule_type) as types')
            ])
            ->whereBetween('schedule_date', $s, $e);

        // 검색
        $project_range = $parsed_uri->getParameter('project_range');
        if ($project_range) $qry->where('i.project_idx', $project_range);
        $title = $parsed_uri->getParameter('title');
        if ($title) $qry->where('i.title', 'like', '%'.$title.'%');
        $content = $parsed_uri->getParameter('content');
        if ($content) $qry->where('i.content', 'like', '%'.$content.'%');
        $importance = (int) $parsed_uri->getParameter('importance');
        if ($importance) $qry->where('i.importance', $importance);
        $work_type = $parsed_uri->getParameter('work_type');
        if ($work_type) $qry->whereIn('i.work_type', explode(',', $work_type));
        $status = $parsed_uri->getParameter('status');
        if ($status) $qry->whereIn('i.status', explode(',', $status));
        $role = $parsed_uri->getParameter('role');
        if ($role) {
            $qry->join(['issue_staff', 's2'], 'i.idx', '=', 's2.issue_idx');
            $qry->whereIn('s2.staff_idx', is_array($role) ? $role : explode(',', $role));
        }

        $plans = $issue_offset = $issue_week_offset = [];
        $res = $qry->groupby('s.schedule_date')
            ->groupby('s.issue_idx')
            ->orderBy('schedule_date', 'asc')
            ->orderBy('issue_idx', 'asc')
            ->get();
        foreach ($res as $plan) {
            // 색상표 구성
            if (!in_array($plan->issue_idx, $issue_offset)) {
                array_push($issue_offset, $plan->issue_idx);
            }
            $plan->issue_offset = array_search($plan->issue_idx, $issue_offset);
            $plan->week_no = (int) date('W', strtotime($plan->schedule_date));

            // 스케쥴표 평탄화 데이터
            if (!isset($issue_week_offset[$plan->week_no])) {
                $issue_week_offset[$plan->week_no] = [$plan->issue_offset, $plan->issue_offset];
            }

            if ($plan->issue_offset < $issue_week_offset[$plan->week_no][0]) {
                $issue_week_offset[$plan->week_no][0] = $plan->issue_offset;
            }
            if ($plan->issue_offset > $issue_week_offset[$plan->week_no][1]) {
                $issue_week_offset[$plan->week_no][1] = $plan->issue_offset;
            }

            // 추가
            if (!isset($plans[$plan->schedule_date])) {
                $plans[$plan->schedule_date] = [];
            }
            array_push($plans[$plan->schedule_date], $plan);
        }

        $this->output([
            'ym' => $ym,
            'lastday' => $last_day,
            'calstart' => $calstart,
            'calend' => $calend,
            'issue_count' => count($issue_offset),
            'plans' => $plans,
            'issue_week_offset' => $issue_week_offset
        ]);
    }

    public function today(ParsedURI $parsedURI) {
        $res = $this->db
            ->table(['issue_schedule' => 's'])
            ->join(['issue', 'i'], 'i.idx', '=', 's.issue_idx')
            ->join(['issue_staff', 'r'], 'r.idx', '=', 's.role_idx')
            ->join(['project', 'p'], 'p.idx', '=', 'i.project_idx')
            ->select(['i.idx', 'i.project_idx',  'i.title', 'r.staff_idx', 'r.role', 's.project_idx', 'p.project_name'])
            ->where('schedule_date', date('Y-m-d'))
            ->groupby('i.idx')->groupby('r.staff_idx')
            ->orderBy('r.staff_idx', 'asc')
            ->orderBy('i.project_idx', 'asc')
            ->orderBy('i.registerd', 'asc')
            ->get();

        $staffs = (new Staff($this->db, $this->config))->snapshot();

        $data = (object) [];
        $issue_offset = [];
        foreach($res as $sch) {
            $sch->project = ['idx' => $sch->project_idx, 'name' => $sch->project_name];
            if (!isset($data->{$sch->staff_idx})) {
                $obj = $staffs[$sch->staff_idx];
                $obj->items = [];
                $data->{$sch->staff_idx} = $obj;
            } else {
                $obj = $data->{$sch->staff_idx};
            }

            if (!isset($issue_offset[$sch->idx])) {
                $issue_offset[$sch->idx] = count($issue_offset);
            }

            $obj->items[] = $sch;
        }
        $data = array_values((array) $data);

        $this->output([
            'status' => 'success',
            'data' => $data,
            'issue_offset' => $issue_offset
        ]);
    }

}