<?php

namespace Wisa\Gcdg\App;

use Wisa\Gcdg\App;
use Wisa\Gcdg\ParsedURI;
use Wisa\Gcdg\App\SVN;
use Wisa\Gcdg\Exceptions\CommonException;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\Renderer\RendererConstant;

class Repository extends App {

    protected $db;
    protected $config;
    private   $repoinfo;
    private   $instance;

    public function __construct($db, $config)
    {
        $this->db = $db;
        $this->config = $config;

        $this->repoinfo = $this->_info();
        $this->instance = new ('Wisa\Gcdg\App\\'.$this->repoinfo->repository_type)(
            $this->repoinfo->repository_user,
            $this->repoinfo->repository_pw,
            $this->repoinfo->repository
        );
    }

    public function logs(ParsedURI $parsed_uri)
    {
        if (!$this->projectPermission('admin,project_admin,developer', $this->currentProjectIdx())) {
            throw new CommonException('저장소 접근 권한이 없습니다.');
        }

        $message = $parsed_uri->getParameter('search_str');
        $type = $parsed_uri->getParameter('args2');
        if ($type == 'search' && !$message) {
            $this->output([
                'status' => 'success',
                'data' => []
            ]);
        }

        $limit = $parsed_uri->getParameter('limit', 100);
        $date_s = $parsed_uri->getParameter('date_s', date('Y-m-d', strtotime('-3 month')));
        $date_e = $parsed_uri->getParameter('date_e', date('Y-m-d'));

        $logs = $this->instance->log($date_s, $date_e, $message);

        $this->output([
            'status' => 'success',
            'data' => $logs
        ]);
    }

    public function diff(ParsedURI $parsedURI)
    {
        if (!$this->projectPermission('admin,project_admin,developer', $this->currentProjectIdx())) {
            throw new CommonException('저장소 접근 권한이 없습니다.');
        }

        $rev = $parsedURI->getParameter('args2');
        if (!$rev) {
            throw new CommonException('리비전 번호를 입력해주세요.');
        }

        $diff = $this->instance->diff($rev);
        if (!$diff) {
            $this->output([
               'status' => 'error',
               'message' => '리비전을 검색할 수 없습니다.'
            ]);
        }

        foreach ($diff->files as $key => $file) {
            $diff->files[$key] = str_replace($this->repoinfo->repository, '', $file);
        }

        $this->output([
           'status' => 'success',
           'data' => $diff
        ]);
    }

    public function diffSource(ParsedURI $parsedURI)
    {
        if (!$this->projectPermission('admin,project_admin,developer', $this->currentProjectIdx())) {
            throw new CommonException('저장소 접근 권한이 없습니다.');
        }

        $rendererName = $parsedURI->getParameter('rendererName');

        $rev = $parsedURI->getParameter('args2');
        $path = $parsedURI->getParameter('path');

        list($old, $new) = $this->instance->diffSource($rev, $path);

        // renderer class name:
        //     Text renderers: Context, JsonText, Unified
        //     HTML renderers: Combined, Inline, JsonHtml, SideBySide
        $rendererName = ($rendererName != 'SideBySide') ? 'Inline' : 'SideBySide';

        // the Diff class options
        $differOptions = [
            // show how many neighbor lines
            // Differ::CONTEXT_ALL can be used to show the whole file
            'context' => 3,
            // ignore case difference
            'ignoreCase' => true,
            // ignore whitespace difference
            'ignoreWhitespace' => true,
        ];

        // the renderer class options
        $rendererOptions = [
            // how detailed the rendered HTML in-line diff is? (none, line, word, char)
            'detailLevel' => 'word',
            // renderer language: eng, cht, chs, jpn, ...
            // or an array which has the same keys with a language file
            // check the "Custom Language" section in the readme for more advanced usage
            'language' => 'eng',
            // show line numbers in HTML renderers
            'lineNumbers' => true,
            // show a separator between different diff hunks in HTML renderers
            'separateBlock' => true,
            // show the (table) header
            'showHeader' => true,
            // the frontend HTML could use CSS "white-space: pre;" to visualize consecutive whitespaces
            // but if you want to visualize them in the backend with "&nbsp;", you can set this to true
            'spacesToNbsp' => true,
            // HTML renderer tab width (negative = do not convert into spaces)
            'tabSize' => 4,
            // this option is currently only for the Combined renderer.
            // it determines whether a replace-type block should be merged or not
            // depending on the content changed ratio, which values between 0 and 1.
            'mergeThreshold' => 0.8,
            // this option is currently only for the Unified and the Context renderers.
            // RendererConstant::CLI_COLOR_AUTO = colorize the output if possible (default)
            // RendererConstant::CLI_COLOR_ENABLE = force to colorize the output
            // RendererConstant::CLI_COLOR_DISABLE = force not to colorize the output
            'cliColorization' => RendererConstant::CLI_COLOR_AUTO,
            // this option is currently only for the Json renderer.
            // internally, ops (tags) are all int type but this is not good for human reading.
            // set this to "true" to convert them into string form before outputting.
            'outputTagAsString' => false,
            // this option is currently only for the Json renderer.
            // it controls how the output JSON is formatted.
            // see available options on https://www.php.net/manual/en/function.json-encode.php
            'jsonEncodeFlags' => \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
            // this option is currently effective when the "detailLevel" is "word"
            // characters listed in this array can be used to make diff segments into a whole
            // for example, making "<del>good</del>-<del>looking</del>" into "<del>good-looking</del>"
            // this should bring better readability but set this to empty array if you do not want it
            'wordGlues' => [' ', '-'],
            // change this value to a string as the returned diff if the two input strings are identical
            'resultForIdenticals' => null,
            // extra HTML classes added to the DOM of the diff container
            'wrapperClasses' => ['diff-wrapper'],
        ];

        $differ = new Differ(explode("\n", $old), explode("\n", $new), $differOptions);
        $renderer = RendererFactory::make($rendererName, $rendererOptions); // or your own renderer object
        $result = $renderer->render($differ);

        $this->output([
           'status' => 'success',
           'differ' => $result
        ]);
    }

    public function info() {
        $data = $this->_info();

        $this->output([
            'status' => 'success',
            'data' => [
                'type' => $data->repository_type,
                'url' => $data->repository
            ]
        ]);
    }

    public function _info()
    {
        if (!$this->projectPermission('admin,project_admin,developer', $this->currentProjectIdx())) {
            throw new CommonException('저장소 접근 권한이 없습니다.');
        }

        $data = $this->db
            ->table('project')
            ->select(['repository', 'repository_type', 'repository_user', 'repository_pw'])
            ->where('idx', $this->currentProjectIdx())
            ->first();

        if (!$data) {
            throw new CommonException('프로젝트를 선택해주세요.');
        }
        if (!$data->repository || !$data->repository_type || !$data->repository_user || !$data->repository_pw) {
            throw new CommonException('프로젝트에 저장소가 설정되어있지 않습니다.');
        }

        return $data;
    }

    public function today()
    {
        $logs = $this->instance->log(null, null, null, 10);

        $this->output([
            'status' => 'success',
            'data' => $logs
        ]);
    }

    public function patch(ParsedURI $parsedURI) {
        if (!$this->projectPermission('admin,project_admin,developer', $this->currentProjectIdx())) {
            throw new CommonException('저장소 접근 권한이 없습니다.');
        }

        $rev = $parsedURI->getParameter('args2');
        if (!$rev) {
            throw new CommonException('리비전 번호가 없습니다.');
        }

        $data = $this->instance->patch($rev);
        if (!$data) {
            throw new CommonException('패치 생성이 불가능한 리비전입니다.');
        }

        $this->output([
            'status' => 'success',
            'data' => $data
        ]);
    }

}