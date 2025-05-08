<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\Exceptions\CommonException;

putenv("LANG=ko_KR.utf8");

class GIT extends App {
    private $username;
    private $password;
    private $repository;
    private $path;

    public function __construct(string $username, string $password, string $repository) {
        $this->username = $username;
        $this->password = $password;
        $this->repository = $repository;
        $this->path = __RES_DIR__.'/repository/'.md5($this->repository);
    }

    public function clone($path)
    {
        if (!is_dir($path)) {
            $url = parse_url($this->repository);
            shell_exec(sprintf('
                git clone %s://%s:%s@%s%s %s',
                $url['scheme'],
                urlencode($this->username),
                urlencode($this->password),
                $url['host'],
                $url['path'],
                $path
            ));
        }
    }

    public function log(?string $date_s, ?string $date_e, ?string $message = '', ?int $limit = 0) {
        shell_exec("git config --global --add safe.directory $this->path");

        $command  = "cd $this->path && ";
        $command .= "git fetch && ";
        $command .= "git log master --oneline --pretty=format:\"%H[spt]%an[spt]%ci[spt]%s[spt]%h%d\" ";
        if ($date_s) {
            $date_s = date('Y-m-d', strtotime('-1 days', strtotime($date_s)));
            $command .= "--since=$date_s ";
        }
        if ($date_e) {
            $command .= "--until=$date_e ";
        }
        if ($message) {
            $message = addslashes($message);
            $command .= "--grep \"$message\"";
        }
        if ($limit > 0) {
            $command .= "-$limit ";
        }
        exec($command, $result);

        $logs = [];
        foreach ($result as $ret) {
            if (!$ret) continue;
            $ret = explode('[spt]', $ret);
            $dateformat = $this->dateformat(new \DateTime($ret[2]));
            if ($date_s == date('Y-m-d', strtotime($dateformat))) continue;

            $tag = '';
            if (str_contains($ret[4], 'tag')) {
                preg_match('/\(tag: ([^)]+)\)/', $ret[4], $tag);
                $tag = $tag[1];
            }

            array_push($logs, [
                'rev' => $ret[0],
                'rev_v' => $ret[4],
                'author' => $ret[1],
                'date' => $dateformat,
                'message' => $ret[3],
                'tag' => $tag
            ]);
        }
        return $logs;
    }

    public function diff(string $rev)
    {
        $command = sprintf(
            'cd %s && git show %s --oneline --name-status --format="%s"',
            $this->path,
            $rev,
            '%H[spt]%an[spt]%ci[spt]%s'
        );
        $show = shell_exec($command);
        if (!$show) return false;

        $show = explode("\n", trim($show));
        $info = explode('[spt]', $show[0]);
        array_shift($show);

        $files = [];
        foreach($show as $path) {
            if (!$path) continue;
            $path = explode("\t", $path);

            switch($path[0]) {
                case 'M' : $item = 'modified'; break;
                case 'A' : $item = 'added'; break;
                case 'D' : $item = 'deleted'; break;
            }

            array_push($files, [
                'item' => $item,
                'kind' => '',
                'path' => $path[1]
            ]);
        }

        $log = (object) [
            'rev' => $info[0],
            'date' => $this->dateFormat(new \DateTime($info[2])),
            'author' => $info[1],
            'msg' => $info[3],
            'files' => $files
        ];

        return $log;
    }

    public function diffSource(string $rev, string $path)
    {
        $old = shell_exec(sprintf(
            'cd %s && git show %s^:%s',
            $this->path,
            $rev,
            $path
        ));

        $new = shell_exec(sprintf(
            'cd %s && git show %s:%s',
            $this->path,
            $rev,
            $path
        ));

        return [$old, $new];
    }

    public function patch(string $rev)
    {
        $command  = "cd $this->path && ";
        $command .= "git pull origin master &&";
        shell_exec($command);

        $command  = "cd $this->path && ";
        $command .= "git diff $rev --oneline";

        return shell_exec($command);
    }


}