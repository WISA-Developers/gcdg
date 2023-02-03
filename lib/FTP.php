<?php

namespace Wisa\Gcdg;

use Wisa\Gcdg\Exceptions\FtpConnectionException;

class FTP {

    private $connection;

    /**
     * FTP 서버에 접속한다
     *
     * @param  json $info
     *
     * @return boolean
     *
     * @throws Exception
     **/
    public function __construct(object $info)
    {
        $this->connection = ftp_connect($info->host, $info->port);
        if (!$this->connection) {
            throw new FtpConnectionException('ftp connection faild');
        }
        if (!ftp_login($this->connection, $info->user, $info->password)) {
            throw new FtpConnectionException('ftp login faild');
        }

        if (isset($info->home)) {
            $this->chdir($info->home);
        }
    }

    /**
     * FTP 현재 폴더 위치를 출력
     *
     * @return string
     *
     **/
    public function pwd()
    {
        return ftp_pwd($this->connection);
    }

    /**
     * 지정한 경로가 폴더인지 체크
     *
     * @param  string $directory
     *
     * @return boolean
     **/
    public function is_dir($directory)
    {
        // return is_array(ftp_mlsd($this->connection, $directory)); 위피 서버에서 ftp_mlsd 미지원

        $current_dir = ftp_pwd($this->connection);
        if (@ftp_chdir($this->connection, $directory)) {
            ftp_chdir($this->connection, $current_dir);
            return true;
        }
        return false;
    }

    /**
     * 지장한 경로가 파일인지 체크
     *
     * @param  string $file
     *
     * @return boolean
     *
     * @throws Exception
     **/
    public function is_file($file)
    {
        return (ftp_size($this->connection, $file) > 0);
    }

    /**
     * 지정한 경로로 이동
     *
     * @param  string $directory
     *
     * @return string
     *
     * @throws FileNotFoundException
     **/
    public function chdir($directory)
    {
        if ($this->is_dir($directory)) {
            ftp_chdir($this->connection, $directory);
            return $this->pwd();
        }

        throw new FtpConnectionException('ftp directory not found : '.$directory);
    }

    /**
     * 재귀적으로 폴더를 생성합니다.
     *
     * @param  string $directory
     **/
    public function mkdir($directory)
    {
        $directory = explode('/', $directory);
        $current = '';
        foreach ($directory as $name) {
            $current .= $name;
            if (!$this->is_dir($current)) {
                ftp_mkdir($this->connection, $current);
            }
            $current .= '/';
        }
    }

    /**
     * FTP로 파일을 업로드합니다.
     *
     * @param  string $local
     * @param  string $remote
     *
     * @return boolean
     *
     * @throws FileUploadException
     **/
    public function upload($local, $remote) {
        $remote_dir = dirname($remote);
        $this->mkdir($remote_dir);

        if (ftp_put($this->connection, $remote, $local, FTP_BINARY) == false) {
            throw new FtpConnectionException('ftp upload error');
        }

        return true;
    }

    /**
     * FTP 파일을 삭제합니다.
     *
     * @param  string $folder
     * @param  string $filename
     *
     * @return boolean
     **/
    public function remove($folder, $filename)
    {
        if ($this->is_file($folder.'/'.$filename)) {
            ftp_delete($this->connection, $folder.'/'.$filename);
            return true;
        }
        return false;
    }

}