<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\Exceptions\CommonException;

putenv("LANG=ko_KR.utf8");

class SVN extends App {
    private $username;
    private $password;
    private $repository;

    public function __construct(string $username, string $password, string $repository) {
        $this->username = $username;
        $this->password = $password;
        $this->repository = $repository;
    }

    public function log(?string $date_s, ?string $date_e, ?string $message = '', ?int $limit = 0) {
        $date_e = date('Y-m-d', strtotime('+1 days', strtotime($date_e)));

        $command  = 'export LC_CTYPE=ko_KR.UTF-8; ';
        $command .= __SVN_PATH__.' log --non-interactive ';
        $command .= " --username $this->username --password $this->password --xml ";
        if ($date_s && $date_e) {
            $command .= " -r\{$date_e\}:\{$date_s\} ";
        }
        if ($message) {
            $command .= " --search \"$message\" ";
        }
        if ($limit) {
            $command .= " -l$limit";
        }
        $command .= " $this->repository";
        $ret = shell_exec($command);
        $result = @json_decode(json_encode(simplexml_load_string($ret)));

        // 검색 된 데이터가 없음
        if (!isset($result->logentry)) {
            return [];
        }
        // 데이터가 1개일 경우
        if (gettype($result->logentry) == 'object') {
            $result->logentry = [$result->logentry];
        }

        $logs = [];
        foreach ($result->logentry as $log) {
            array_push($logs, [
                'rev' => $log->{'@attributes'}->revision,
                'rev_v' => 'r'.$log->{'@attributes'}->revision,
                'author' => $log->author,
                'date' => $this->dateformat(new \DateTime($log->date)),
                'message' => $log->msg
            ]);
        }
        return $logs;
    }

    public function today(int $limit = 10) {
        $ret = shell_exec(sprintf(
            'export LC_CTYPE=ko_KR.UTF-8;'.__SVN_PATH__.' log --non-interactive --username %s --password %s --xml -l%d %s',
            $this->username,
            $this->password,
            $limit,
            $this->repository
        ));
        $result = @json_decode(json_encode(simplexml_load_string($ret)));

        // 검색 된 데이터가 없음
        if (!isset($result->logentry)) {
            return [];
        }
        // 데이터가 1개일 경우
        if (gettype($result->logentry) == 'object') {
            $result->logentry = [$result->logentry];
        }

        $logs = [];
        foreach ($result->logentry as $log) {
            array_push($logs, [
                'rev' => $log->{'@attributes'}->revision,
                'rev_v' => 'r'.$log->{'@attributes'}->revision,
                'author' => $log->author,
                'date' => $this->dateformat(new \DateTime($log->date)),
                'message' => $log->msg
            ]);
        }
        return $logs;
    }

    public function diff(string $rev)
    {
        $log = shell_exec(sprintf(
            __SVN_PATH__.' log -r%s --non-interactive --username %s --password %s --xml %s',
            $rev,
            $this->username,
            $this->password,
            $this->repository
        ));
        $log = @json_decode(json_encode(simplexml_load_string($log)));
        if (!$log) return false;

        $diff = shell_exec(sprintf(
            __SVN_PATH__.' diff --summarize --xml -r%s:%s --username %s --password %s %s',
            ($rev-1),
            $rev,
            $this->username,
            $this->password,
            $this->repository
        ));
        $xml = simplexml_load_string($diff);

        $files = [];
        foreach($xml->paths->path as $path) {
            array_push($files, [
                'item' => $path['item'],
                'kind' => $path['kind'],
                'path' => $path[0]
            ]);
        }

        $date = new \DateTime($log->logentry->date, new \DateTimeZone('UTC'));
        $date = $date->setTimezone(new \DateTimeZone('Asia/Seoul'));

        $log->logentry->rev = $log->logentry->{'@attributes'}->revision;
        $log->logentry->date = $this->dateFormat($date);
        $log->logentry->files = $files;

        return $log->logentry;
    }

    public function diffSource(int $rev, string $path)
    {
        $old = shell_exec(sprintf(
            __SVN_PATH__.' cat -r%s --username %s --password %s %s',
            ($rev-1),
            $this->username,
            $this->password,
            $this->repository.'/'.$path
        ));

        $new = shell_exec(sprintf(
            __SVN_PATH__.' cat -r%s --username %s --password %s %s',
            $rev,
            $this->username,
            $this->password,
            $this->repository.'/'.$path
        ));

        return [$old, $new];
    }

    public function patch($rev) {
        $command = sprintf(__SVN_PATH__." diff -r%d:%d --username %s --password %s %s",
            $rev-1,
            $rev,
            $this->username,
            $this->password,
            $this->repository
        );

        return shell_exec($command);
    }
}