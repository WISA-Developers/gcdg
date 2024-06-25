<template>
	<div id="issueTicket" class="layoutCenter">
		<form class="contentArea" @submit.prevent="submit">
			<input type="hidden" name="idx" :value="data.idx">
			<input type="hidden" name="hash" :value="data.hash">

			<h2 class="title"><i class="xi-pen xi-1x"></i> 이슈 등록</h2>
			<table class="table tableVertical">
				<colgroup>
					<col>
					<col style="width: 100%">
				</colgroup>
				<tbody>
				<tr>
					<th>제목</th>
					<td>
						<input type="text" name="title" v-model="data.title" class="input_text input_full">
					</td>
				</tr>
				<tr>
					<th>업무 종류</th>
					<td>
						<label v-for="(value, key) in define_config.work_type">
							<input type="radio" name="work_type" v-model="data.work_type" :value="key"> {{ value }}
						</label>
					</td>
				</tr>			
				<tr>
					<th>상태</th>
					<td>
						<label v-for="(value, key) in define_config.issue_stat">
							<input type="radio" name="status" v-model="data.status" :value="key"> {{ value }}
						</label>
					</td>
				</tr>
				<tr>
					<th>일정</th>
					<td>
						<div class="cal">
							<datepicker name="plan_s" :date="data.plan_s"></datepicker>
							<datepicker name="plan_e" :date="data.plan_e"></datepicker>
						</div>
					</td>
				</tr>
				<tr>
					<th>기획</th>
					<td>
						<staffs_search 
							name="planner" 
							source="staff/list" 
							:search.sync="data.planner" 
						></staffs_search>
					</td>
				</tr>
				<tr>
					<th>디자인</th>
					<td>
						<staffs_search 
							name="designer" 
							source="staff/list" 
							:search.sync="data.designer" 
						></staffs_search>
					</td>
				</tr>
				<tr>
					<th>퍼블리싱</th>
					<td>
						<staffs_search 
							name="publisher" 
							source="staff/list" 
							:search.sync="data.publisher" 
						></staffs_search>
					</td>
				</tr>
				<tr>
					<th>개발</th>
					<td>
						<staffs_search 
							name="developer" 
							source="staff/list" 
							:search.sync="data.developer" 
						></staffs_search>
					</td>
				</tr>
				<tr>
					<th>검수</th>
					<td>
						<staffs_search 
							name="tester" 
							source="staff/list" 
							:search.sync="data.tester" 
						></staffs_search>
					</td>
				</tr>
				<tr>
					<th>참조자</th>
					<td>
						<staffs_search 
							name="referer" 
							source="staff/list" 
							:search.sync="data.referer" 
						></staffs_search>
					</td>
				</tr>
                <tr>
                    <th>최종확인자</th>
                    <td>
                        <staffs_search
                            name="checker"
                            source="staff/list"
                            :search.sync="data.checker"
                        ></staffs_search>
                    </td>
                </tr>
				<tr>
					<th>수정권한</th>
					<td>
						<select name="permission" v-model="data.permission" class="input_select">
							<option value="2">등록자와 담당자</option>
                            <option value="1">등록자만 수정</option>
							<option value="0">전체</option>
						</select>
					</td>
				</tr>
				<tr>
					<th>중요도</th>
					<td>
						<div class="input_importance checkable">
							<label class="xi-star-o xi-2x"><input type="radio" name="importance" value="1" v-model="data.importance"></label>
							<label class="xi-star-o xi-2x"><input type="radio" name="importance" value="2" v-model="data.importance"></label>
							<label class="xi-star-o xi-2x"><input type="radio" name="importance" value="3" v-model="data.importance"></label>
							<label class="xi-star-o xi-2x"><input type="radio" name="importance" value="4" v-model="data.importance"></label>
							<label class="xi-star-o xi-2x"><input type="radio" name="importance" value="5" v-model="data.importance"></label>
						</div>
					</td>
				</tr>
				<tr>
					<th>저장소 리비전</th>
					<td>
						<repo_search 
							name="repository" 
							source="repository/logs/search"
							:search.sync="data.repository" 
						></repo_search>
					</td>
				</tr>
                <tr>
                    <th>관련 계정</th>
                    <td>
                        <account_search
                            name="account_idx"
                            source="issue/epAccount"
                            :search.sync="data.account"
                        ></account_search>
                    </td>
                </tr>
				<tr>
					<th>디바이스</th>
					<td>
						<label v-for="device in define_config.devices">
							<input type="checkbox" name="device[]" v-model="data.device" :value="device"> {{ device }}
						</label>
					</td>
				</tr>
				<tr>
					<td colspan="2" style="padding: 10px 0">
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
	data: function() {
		return {
			data: {
				hash: Date.now(),
				work_type: Object.keys(this.define_config.work_type).at(0),
				status: Object.keys(this.define_config.issue_stat).at(0),
				importance: '0',
				permission: '0',
				device: [],
				referer: []
			}
		}
	},
	watch: {
		'data.importance': function(n) {
			const importance = document.querySelectorAll('input[name=importance]');
			importance.forEach(function(star, key) {
				star.parentNode.classList.remove('xi-star', 'xi-star-o');
				star.parentNode.classList.add(n > key ? 'xi-star' : 'xi-star-o');
			});
		},
	},
	methods: {
		submit: function(e) {
			printLoading();

			const form = e.target;
			if (form.disabled == true) return false;
			form.disabled = true;
			form.content.value = this.Editor.getMarkdown();

			api('/api/issue/set', 'post', form)
				.then((ret) => {
					removeLoading();
					if (ret.status == 'success') {
						this.goList();
					}
					form.disabled = false;
				});
			return false;
		},
		goList: function() {
			this.$router.go(-1);
		},
		setEditor: function() {
			document.querySelector('#editor').innerHTML = '';
			const { Editor } = toastui;
			const { codeSyntaxHighlight } = Editor.plugin;
			this.Editor = new toastui.Editor({
				el: document.querySelector('#editor'),
				height: '600px',
				initialEditType: 'markdown',
				previewStyle: (document.body.clientWidth > 800) ? 'vertical' : 'tab',
				previewHighlight: false,
				initialValue: this.data.content,
				autofocus: false,
				hooks: {
					addImageBlobHook: (blob, callback) => {
						ajaxUpload(
							{hash: this.data.hash, uploader: 'editor', referer: 'issue'}, 
							blob, 
							callback
						);
					}
				},
				plugins: [codeSyntaxHighlight],
				theme: (window.localStorage.getItem('site-darkmode') == 'Y') ? 'dark' : ''
			});
		}
	},
	mounted: function() {
		const idx = this.$route.params.idx | '';
		if (idx) {
			api('/api/issue/get/'+idx)
				.then((ret) => {
					if (ret.status == 'success') {
						if (!ret.permission) {
							window.alert('수정 권한이 없습니다.');
							this.goList();
							return false;
						}
						this.data = ret.data;
						this.setEditor();
					}
				});
		} else {
			this.data.importance = 1;
			this.setEditor();
		}
	},
	components: {
		'datepicker': datepicker,
		staffs_search: Vue.defineAsyncComponent(() => loadModule('/resource/view/common/staffs_search.vue', options)),
		repo_search: Vue.defineAsyncComponent(() => loadModule('/resource/view/common/repo_search.vue', options)),
        account_search: Vue.defineAsyncComponent(() => loadModule('/resource/view/common/account_search.vue', options)),
	}
}
</script>