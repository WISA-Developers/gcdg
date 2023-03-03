<template>
	<table id="component_comment" class="table tableHorizontal flexible">
		<colgroup>
            <col>
            <col>
            <col style="width: 75%">
		</colgroup>
		<thead>
		<tr>
			<th>작성일시</th>
			<th>작성자</th>
			<th>내용</th>
		</tr>
		</thead>
		<tbody>
		<tr v-for="comment in comments">
			<td class="date">{{ comment.registerd  }} </td>
			<td>{{ comment.creater.name }}</td>
			<td class="left content" style="padding-right: 60px">
				<div class="issue_comment_content" :data-idx="comment.idx" v-html="comment.content"></div>
				<div class="buttons">
					<a href="#" @click.prevent="modify(comment.idx)"><i class="xi-pen"></i></a>
					<a href="#" @click.prevent="remove(comment.idx)"><i class="xi-trash"></i></a>
				</div>
			</td>
		</tr>					
		</tbody>
	</table>
	<div>
		<div id="comment"></div>
	</div>
	<div class="bottom">
		<div>
			<label style="font-size: 0.9em;"><input type="checkbox" v-model="push"> 사내 메신저로 수신자에게 알림</label>
		</div>
        <div class="page_block" v-html="paginator"></div>
        <div class="button_block">
			<span class="button ok"><input type="button" value="등록" @click="set(null)"></span>
		</div>
	</div>

	<div class="issue_comment_modify">
		<div class="dimmed"></div>
		<div class="form">
			<h2>
				<i class="xi-pen"></i>&nbsp;&nbsp;코멘트 수정
			</h2>
			<div id="comment_modify"></div>
			<div class="bottom">
				<div class="button_block">
					<span class="button"><input type="button" value="수정" @click="submitModify"></span>
					<span class="button cancel"><input type="button" value="닫기" @click="cancelModify"></span>
				</div>
			</div>
		</div>
	</div>
</template>

<style scope>
.issue_comment_modify {
	display: none;
	align-items: center;
	position: fixed;
	z-index: 97;
	top: 0;
	left: 0;
	width: 100vw;
	height: 100vh;
}

.issue_comment_modify > div > h2 {
	display: flex;
	align-items: center;
	height: var(--gnb-height);
	padding: 0 20px;
    background: var(--gnb-color);
    color: #fff;
}

.issue_comment_modify > div h2 * {
	color: #fff;
}

.issue_comment_modify .dimmed {
	z-index: 98;
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: #000;
	opacity: .5;
}

.issue_comment_modify .form {
	position: relative;
	z-index: 99;
	width: 80%;
	margin: 0 auto;
	padding: 10px;
	background-color: var(--main-background-color);
}

.issue_comment_content img {
	max-height: 100px;
	margin-right: 5px;
}
</style>

<script>
const { Editor } = toastui;
const { codeSyntaxHighlight } = Editor.plugin;

export default {
	data: function() {
		return {
			hash: Date.now(),
			push: true,
			page: 0,
			paginator: null,
			comments: [],
			modify: {},
			viewer: []
		}
	},
	props: {
		data: Array
	},
	methods: {
		hashchanged: function() {
			this.reload()
		},
		reload: function() {
			const uri = new URL(location.href);
			const suri = new URL(uri.origin+'?'+uri.hash.split('?').at(1));
			const page = suri.searchParams.get('c') || 1;

			api('/api/comment/list/'+this.$route.params.idx+'/'+page+'?limit=5')
				.then((ret) => {
					if (ret.status == 'success') {
						if (ret.count > 0 && ret.data.length == 0) { // 삭제 후 현재 페이지 없을 경우
							ret.page--;
							sethash({c: ret.page, l: 'c'});
							return false;
						}

						this.page = ret.page;
						this.paginator = ret.paginator;
						this.comments = ret.data.reverse();

						this.$emit('get-data', 'component_comment_count', ret.count);
					}
				});
		},
		set: function(req) {
			printLoading();

			if (!req) { // 신규 등록
				req = {
					idx: 0,
					issue_idx: this.data.idx,
					hash: this.hash,
					content: this.Editor.getMarkdown(),
					push: this.push
				};
				this.Editor.setHTML('');
			}

			api('/api/comment/set', 'post', req)
				.then((ret) => {
					removeLoading();
					if (ret.status == 'success') {
						if (req.idx == 0) {
							this.hash = Date.now();
							sethash({c: 1, l: 'c'});
						} else {
							this.cancelModify();
						}
						this.reload();
					}
				});
		},
		remove: function(idx) {
			if (!window.confirm('선택하신 코멘트를 삭제하시겠습니까?')) {
				return false;
			}

			api('/api/comment/remove/'+idx)
				.then((ret) => {
					if (ret.status == 'success') {
						sethash({l: 'c'});
						this.reload();
					}
				});
		},
		modify: function(idx) {
			api('/api/comment/get/'+idx)
				.then((ret) => {
				if (ret.status == 'success') {
					$('.issue_comment_modify').css('display', 'flex');

					// 수정 폼
					this.modify = ret.data;
					this.ModifyEditor = new toastui.Editor({
						el: document.querySelector('#comment_modify'),
						height: '70vh',
						initialEditType: 'wysiwyg',
						previewHighlight: false,
						initialValue: ret.data.content.toString(),
						autofocus: false,
						hooks: {
							addImageBlobHook: function(blob, callback) {
								ajaxUpload(
									{hash: ret.data.hash, uploader: 'editor', referer: 'comment'}, 
									blob, 
									callback
								);
							}
						},
						plugins: [codeSyntaxHighlight]
					});
				}
			});
		},
		submitModify: function() {
			this.set({
				idx: this.modify.idx,
				issue_idx: this.data.idx,
				content: this.ModifyEditor.getMarkdown()
			});
		},
		cancelModify: function() {
			$('.issue_comment_modify').fadeOut();
		}
	},
	mounted: function() {
		this.hashchanged();

		// 등록폼
		document.querySelector('#comment').innerHTML = '';
		this.Editor = new toastui.Editor({
			el: document.querySelector('#comment'),
			height: '300px',
			initialEditType: 'wysiwyg',
			previewHighlight: false,
			initialValue: '',
			autofocus: false,
			hooks: {
				addImageBlobHook: (blob, callback) => {
					console.log(this.hash);
					ajaxUpload(
						{hash: this.hash, uploader: 'editor', referer: 'comment'}, 
						blob, 
						callback
					);
				}
			},
			plugins: [codeSyntaxHighlight],
			theme: (window.localStorage.getItem('site-darkmode') == 'Y') ? 'dark' : ''
		});
	},
	updated: function() {
		// markdown parse
		this.viewer.forEach(function(E) {
			E.destroy();
		});
		this.viewer = [];

		document.querySelectorAll('.issue_comment_content').forEach((o, key) => {
			let html = this.comments[key].content.toString()
				.replace(/&lt;/gi, '<')
				.replace(/&gt;/gi, '>')
				.replace(/-&amp;amp;gt;/gi, '->');

			this.viewer.push(
				toastui.Editor.factory({
					el: o,
					viewer: true,
					initialValue: html,
					theme: (window.localStorage.getItem('site-darkmode') == 'Y') ? 'dark' : 'basic',
					events: {
						load: function(r) {
							// 이미지 크게 보기
							r.preview.previewContent.querySelectorAll('img').forEach(function(img) {
								img.style.cursor = 'pointer';
								img.addEventListener('click', function(e) {
									imageViewer(this.src);
								});
							});
						}
					},
					plugins: [codeSyntaxHighlight]
				})
			);
		});
	}
}
</script>