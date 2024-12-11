<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\App\Issue;
use Wisa\Gcdg\Exceptions\FileNotFoundException;
use Wisa\Gcdg\FTP;
use Wisa\Gcdg\ParsedURI;
use Wisa\Gcdg\Exceptions\CommonException;
use JasonGrimes\Paginator;
use Zeuxisoo\Core\Validator;
use Symfony\Component\HttpClient\HttpClient;
use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;

class File extends App {

    protected QueryBuilderHandler $db;
    protected $config;

    public function __construct(QueryBuilderHandler $db, $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function upload()
    {
        $validator = Validator::factory($_POST);
        $validator->add('hash', 'Pleas enter hash')->rule('required');
        $validator->add('uploader', 'Pleas enter uploader')->rule('required');
        $validator->add('referer', 'Pleas enter referer')->rule('required');
        if ($validator->inValid()) {
            throw new CommonException($validator->firstError());
        }

        $file = $_FILES['image'];
        $hash = $_POST['hash'];
        $uploader = $_POST['uploader'];
        $referer = $_POST['referer'];

        if (preg_match('/image\.([a-z]+)$/', $file['name'], $tmp)) {
            $file['name'] = 'clipboard_'.str_replace('.', '', microtime(true)).'.'.$tmp[1];
        }

        if (!isset($file['size'])) {
            throw new \InvalidArgumentException('업로드 파일의 용량이 확인되지 않습니다.');
        }
        if ($uploader == 'editor') {
            try {
                $imagick = new \Imagick(realpath($file['tmp_name']));
                if (!$imagick->getImageFormat()) {
                    throw new CommonException('이미지파일만 업로드 할 수 있습니다.');
                }
            } catch(\ImagickException $e) {
                throw new CommonException('이미지파일만 업로드 할 수 있습니다.');
            }
        }

        $dir = $this->makeUploadDirectory($hash);
        $filename = $this->makeFilename($file['name']);

        $ftp = new FTP($this->config->fileserver->ftp);
        if (!$ftp->upload($file['tmp_name'], $dir[1].'/'.$filename)) {
            throw new CommonException('file upload error');
        }

        $idx = $this->insert($uploader, $referer, $hash, $dir[1], $filename, $file);

        $this->output([
            'status' => 'success',
            'filename' => $dir[0].$dir[1].'/'.$filename
        ]);
    }

    private function insert(String $uploader, String $referer, String $hash, String $folder, String $filename_n, Array $file)
    {
        $active = ($uploader == 'editor') ? 'N' : 'Y';

        $parent_idx = 0;
        if ($referer == 'files') {
            $parent_idx = ($this->db
                ->table('issue')
                ->select(['idx'])
                ->where('hash', $hash)
                ->first())->idx;
        }

        return $this->db
            ->table('asset_files')
            ->insert([
                'hash' => $hash,
                'referer' => $referer,
                'parent_idx' => $parent_idx,
                'uploader' => $uploader,
                'creater_idx' => $this->currentStaffIdx(),
                'folder' => $folder,
                'filename' => $filename_n,
                'origin' => $file['name'],
                'mime' => $file['type'],
                'extension' => pathinfo($file['name'], PATHINFO_EXTENSION),
                'filesize' => $file['size'],
                'registerd' => $this->db->raw('now()'),
                'active' => $active
            ]);
    }

    public function list(ParsedURI $parsed_uri)
    {
        $hash = $parsed_uri->getParameter('hash');

        $page = (int) $parsed_uri->getParameter('args2', 1);
        $limit = (int) $parsed_uri->getParameter('limit', 5);

        $data = $this->__list($hash, $page, $limit, $parsed_uri);

        $this->output([
            'status' => 'success',
            'data' => $data[0],
            'page' => $data[1],
            'paginator' => $data[2],
            'count' => $data[3]
        ]);
    }

    public function listAll(ParsedURI $parsed_uri)
    {
        $hash = $parsed_uri->getParameter('hash');

        $page = (int) $parsed_uri->getParameter('page', 1);
        $limit = (int) $parsed_uri->getParameter('limit', 10);

        $data = $this->__list($hash, $page, $limit, $parsed_uri);

        $this->output([
            'status' => 'success',
            'data' => $data[0],
            'page' => $data[1],
            'paginator' => $data[2],
            'count' => $data[3]
        ]);
    }

    public function __list(?String $hash, int $page = 1, int $limit = 10, ParsedURI $parsed_uri)
    {
        // 조회 권한
        if ($hash) {
            $issue = $this->db
                ->table('issue')
                ->where('hash', $hash)
                ->first();
            if ($issue && !(new Issue($this->db, $this->config))->issuePermission('read', $issue)) {
                throw new CommonException('파일 목록 접근 권한이 없습니다.');
            }
        }

        $qry = $this->db
            ->table(['asset_files' => 'f'])
            ->where('active', 'Y')
            ->whereIn('referer', ['issue', 'comment', 'files']);
        if ($hash) {
            $qry->select(['f.*'])
                ->where('hash', $hash);
            $page_pattern = "javascript:sethash({f:'(:num)', l:'f'})";
        } else {
            $qry->select(['f.*', 'i.idx' => 'issue_idx', 'i.title' => 'issue'])
                ->join(['issue', 'i'], 'f.parent_idx', '=', 'i.idx')
                ->where('i.project_idx', $this->currentProjectIdx())
                ->groupBy('f.idx');
            $page_pattern = "javascript:sethash({page:'(:num)'})";
        }

        // 검색
        $issue = $parsed_uri->getParameter('issue');
        if ($issue) $qry->where('i.title', 'like', "%$issue%");
        $origin = $parsed_uri->getParameter('origin');
        if ($origin) $qry->where('f.origin', 'like', "%$origin%");
        $creater = $parsed_uri->getParameter('creater');
        if ($creater) $qry->whereIn('f.creater_idx', explode(',', $creater));
        $registerd_s = $parsed_uri->getParameter('registerd_s');
        $registerd_e = $parsed_uri->getParameter('registerd_e');
        if ($registerd_s && $registerd_e) {
            $qry->whereBetween('f.registerd', $registerd_s, $registerd_e.' 23:59:59');
        } else {
            if ($registerd_s) $qry->where('f.registerd', '>=', $registerd_s);
            if ($registerd_e) $qry->where('f.registerd', '<=', $registerd_e.' 23:59:59');
        }
        $extension = $parsed_uri->getParameter('extension');
        if ($extension) {
            $extension = explode(',', $extension);

            $ext = [];
            if (in_array('xls', $extension)) array_push($ext, 'xls', 'xlsx');
            if (in_array('doc', $extension)) array_push($ext, 'doc', 'docx');
            if (in_array('ppt', $extension)) array_push($ext, 'ppt', 'pptx');
            if (in_array('pdf', $extension)) array_push($ext, 'pdf');
            if (in_array('txt', $extension)) array_push($ext, 'txt');
            if (in_array('image', $extension)) array_push($ext, 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp');
            $qry->whereIn('extension', $ext);
        }

        $count = $qry->count();
        $paginator = new Paginator(
            $count,
            $limit,
            $page,
            $page_pattern
        );
        $paginator->setMaxPagesToShow(5);

        // 스탭 정보 스냅샷
        $_staffs = (new Staff($this->db, $this->config))->snapshot();

        $res = $qry
            ->orderBy('f.idx', 'DESC')
            ->limit($limit)
            ->offset(($page-1)*$limit)
            ->get();
        foreach ($res as $data) {
            $data->creater = $_staffs[$data->creater_idx];
            $data->registerd = $this->dateformat(new \DateTime($data->registerd));
            $data->url = $this->getFileURL($data->folder, $data->filename);
            $data->filesize_human = $this->filesizeHuman($data->filesize);
            $data->mime = preg_replace('/.*\//', '', $data->mime);
        }

        $paginator->setMaxPagesToShow(10);
        $paginator->setPreviousText('');
        $paginator->setNextText('');

        return [$res, $page, (string) $paginator, $count];
    }

    public function getFileURL(String $folder, String $filename)
    {
        return $this->config->fileserver->url.'/'.$folder.'/'.$filename;
    }

    public function download(ParsedURI $parsed_uri)
    {
        $idx = (int) $parsed_uri->getParameter('args2');
        if (!$idx) {
            throw new CommonException('no file idx');
        }

        $file = $this->db
            ->table(['asset_files' => 'f'])
            ->join(['issue', 'i'], 'f.parent_idx', '=', 'i.idx')
            ->select(['i.idx' => 'issue_idx', 'f.*'])
            ->where('idx', $idx)
            ->first();

        if (!$file) {
            throw new FileNotFoundException('파일이 존재하지 않습니다.');
        }

        // 다운로드 권한
        if (!(new Issue($this->db, $this->config))->issuePermission('read', $file->issue_idx)) {
            throw new CommonException('파일 다운로드권한이 없습니다.');
        }

        $client = HttpClient::create();
        $response = $client->request('GET', $this->getFileURL($file->folder, $file->filename));
        $content = $response->getContent();
        $mime = getimagesizefromstring($content);

        if ($mime && $mime['mime']) {
            header('Content-Type: '.$mime['mime']);
            Header('Content-Disposition: inline; filename='.$file->origin);
            Header('Content-Length: '.$file->filesize);
        } else {
            // 다운로드 실행
            header('Content-Type: application/force-download');
            Header('Content-Disposition: attachment; filename='.$file->origin);
            Header('Content-Length: '.$file->filesize);
            header('Content-Transfer-Encoding: binary ');
            Header('Pragma: no-cache');
            Header('Expires: 0');
        }
        exit($content);
    }

    public function remove(ParsedURI $parsed_uri)
    {
        $idx = (int) $parsed_uri->getParameter('args2');
        if (!$idx) {
            throw new CommonException('no file idx');
        }

        $file = $this->db
            ->table(['issue' => 's'])
            ->select(['s.idx' => 'issue_idx', 'f.*'])
            ->join(['asset_files', 'f'], 's.hash', '=', 'f.hash')
            ->where('f.idx', $idx)
            ->first();
        if (!$file) {
            throw new CommonException('존재하지 않는 파일입니다.');
        }

        // 삭제 권한
        if (!(new Issue($this->db, $this->config))->issuePermission('write', $file->issue_idx)) {
            throw new CommonException('파일 삭제 권한이 없습니다.');
        }

        $ftp = new FTP($this->config->fileserver->ftp);
        $ftp->remove($file->folder, $file->filename);

        $this->db
            ->table('asset_files')
            ->where('idx', $idx)
            ->delete();

        $this->output([
            'status' => 'success',
            'message' => '파일이 삭제되었습니다.'
        ]);
    }

    public function activeByMarkDown(int $parent_idx, string $hash, string $content)
    {
        $this->db
            ->table('asset_files')
            ->where('hash', $hash)
            ->where('uploader', 'editor')
            ->update(['active' => 'N']);

        preg_match_all('/\((http[^\)]+)\)/', $content, $matches);
        $this->_activeFile($parent_idx, $hash, $matches[1]);
    }

    public function activeByHTML(int $parent_idx, string $hash, string $content)
    {
        $this->db
            ->table('asset_files')
            ->where('hash', $hash)
            ->where('uploader', 'editor')
            ->update(['active' => 'N']);
        $matches = [];
        $dom = new \DOMDocument();
        $dom->loadHTML($content);
        $imgs = $dom->getElementsByTagName('img');
        foreach ($imgs as $img) {
            $matches[] = $img->getAttribute('src');
        }
        $this->_activeFile($parent_idx, $hash, $matches);
    }

    private function _activeFile(int $parent_idx, string $hash, array $matches)
    {
        foreach ($matches as $filename) {
            $filename = parse_url(str_replace($this->config->fileserver->url, '', $filename));
            $folder = trim(dirname($filename['path']), '/');
            $filename = basename($filename['path']);

            $this->db
                ->table('asset_files')
                ->where('hash', $hash)
                ->where('filename', '=', $filename)
                ->update([
                    'parent_idx' => $parent_idx,
                    'active' => 'Y'
                ]);
        }
    }

    public function toggleActive($hash, $value)
    {
        if ($value != 'Y' && $value != 'N') {
            throw new CommonException('상태는 Y 또는 N만 선택할 수 있습니다.');
        }
        $this->db
            ->table('asset_files')
            ->where('hash', $hash)
            ->where('uploader', 'editor')
            ->update(['active' => $value]);
    }

}