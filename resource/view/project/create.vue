<template>
	<div id="project" class="layoutCenter">
		<form class="contentArea" @submit.prevent="submit">
			<input type="hidden" name="idx" :value="data.idx">
			<input type="hidden" name="hash" :value="data.hash">

			<h2 class="title"><i class="xi-pen xi-1x"></i> 프로젝트 등록</h2>
			<table class="table tableVertical">
				<colgroup>
					<col>
					<col style="width: 100%">
				</colgroup>
				<tbody>
				<tr>
					<th>프로젝트명</th>
					<td>
						<input type="text" name="project_name" v-model="data.project_name" class="input_text input_full">
					</td>
				</tr>
				<tr>
					<th>상태</th>
					<td>
						<label v-for="(value, key) in define_config.project_stat">
							<input type="radio" name="status" v-model="data.status" :value="key" :checked="data.status==key"> {{ value }}
						</label>
					</td>
				</tr>
				<tr>
					<th>프로젝트 관리자</th>
					<td>
						<staffs_search 
							name="admin" 
							source="staff/list" 
							:search.sync="data.admin" 
							:staff_snapshot="staff_snapshot"
						></staffs_search>
					</td>
				</tr>
				<tr>
					<th>프로젝트 참여자</th>
					<td>
						<staffs_search 
							name="member" 
							source="staff/list" 
							:search.sync="data.member" 
						></staffs_search>
					</td>
				</tr>
				<tr>
					<th>저장소 주소</th>
					<td>
						<div>
							<label><input type="radio" name="repository_type" v-model="data.repository_type" value="SVN"> SVN</label>
							<label><input type="radio" name="repository_type" v-model="data.repository_type" value="GIT"> GIT</label>
						</div>
						<input type="text" name="repository" v-model="data.repository" class="input_text input_full">
					</td>
				</tr>
				<tr>
					<th>저장소 아이디</th>
					<td>
						<input type="text" name="repository_user" v-model="data.repository_user" class="input_text" autocomplete="false">
					</td>
				</tr>
				<tr>
					<th>저장소 패스워드</th>
					<td>
						<input type="password" name="repository_pw" v-model="data.repository_pw" class="input_text" autocomplete="new-password">
					</td>
				</tr>
				<tr>
					<th>저장소 접속권한</th>
					<td>
						<staffs_search 
							name="developer" 
							source="staff/list" 
							:search.sync="data.developer" 
						></staffs_search>
					</td>
				</tr>
				<tr>
					<th>프로젝트 소개</th>
					<td>
						<div id="editor"></div>
						<textarea id="content" name="content" style="display:none">{{ data.content }}</textarea>
					</td>
				</tr>
				</tbody>
			</table>
			<div class="bottom">
				<div class="button_block">
					<span class="button ok"><input type="submit" value="확인"></span>
					<span class="button"><input type="button" value="뒤로" @click="goList"></span>
				</div>
			</div>
		</form>
	</div>
</template>

<script type="module">
export default {
	inject: ['define_config', 'staff_snapshot'],
	data() {
		return {
			data: {
				hash: Date.now(),
				status: Object.keys(this.define_config.project_stat).at(0),
				admin: [],
				member: [],
				developer: []
			}
		}
	},
	methods: {
		submit: function(e) {
			printLoading();

			const form = e.target;
			if (form.disabled == true) return false;
			form.disabled = true;
			form.content.value = this.Editor.getHTML();

			api('/api/project/set', 'post', form)
				.then((ret) => {
					removeLoading();
					if (ret.status == 'success') {
						this.goList();
					}
					form.disabled = false;
				});
			return false;
		},
		setEditor: function() {
			document.querySelector('#editor').innerHTML = '';
			const { Editor } = toastui;
			const { codeSyntaxHighlight } = Editor.plugin;
			this.Editor = new toastui.Editor({
				el: document.querySelector('#editor'),
				height: '400px',
				initialEditType: 'wysiwyg',
				previewStyle: 'vertical',
				previewHighlight: false,
				initialValue: this.data.content,
				autofocus: false,
				hooks: {
					addImageBlobHook: (blob, callback) => {
						ajaxUpload(
							{hash: this.data.hash, uploader: 'editor', referer: 'project'},
							blob, 
							callback
						);
					}
				},
				plugins: [codeSyntaxHighlight],
				theme: (window.localStorage.getItem('site-darkmode') == 'Y') ? 'dark' : ''
			});
		},
		goList: function() {
			this.$router.go(-1);
		}
	},
	mounted: function() {
		const idx = this.$route.params.idx | '';
		if (idx) {
			api('/api/project/get/'+idx)
				.then((ret) => {
                    if (ret.status == 'success') {
					    this.data = ret.data;
					    this.setEditor();
                    } else {
                        this.$router.go(-1);
                    }
				});
		} else {
			this.setEditor();
		}
	},
	components: {
		staffs_search: Vue.defineAsyncComponent(() => loadModule('/resource/view/common/staffs_search.vue', options))
	}

}
</script>