<template>
	<div class="layoutHorizontal">
		<div class="leftArea">
			<div style="text-align: right">
				<div>
					<span class="button"><input type="button" value="프로젝트변경" @click="changeProject"></span>
					<span class="button cancel"><input type="button" value="Sign out" @click="signout"></span>
				</div>
				<mypage_summary></mypage_summary>
			</div>
		</div>

		<div class="contentArea">
			<div id="config">
				<h2 class="title"><i class="xi-cog xi-1x"></i> 사이트 설정</h2>
				<table class="table tableVertical">
					<colgroup>
						<col style="width: 130px">
					</colgroup>
					<tbody>
						<tr>
							<th>다크모드</th>
							<td>
								<label><input type="radio" v-model="darkmode" value="Y"> 사용</label>
								<label><input type="radio" v-model="darkmode" value="N"> 사용 안함</label>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<br>

			<div id="apiList">
				<h2 class="title"><i class="xi-rss xi-1x"></i> API 키 관리</h2>

				<table class="table tableHorizontal flexible">
					<colgroup>
							<col style="width:130px;">
							<col>
							<col>
					</colgroup>
					<thead>
					<tr>
						<th>생성일시</th>
						<th>API Key</th>
						<th>API 사용 용도</th>
					</tr>
					</thead>
					<tbody>
						<tr v-if="!loading" v-for="api in list">
							<td class="date">{{ api.registerd }}</td>
							<td class="left" style="font-size: 0.9em">
								{{ api.apikey }}
								<span class="button"><input type="button" value="복사" @click="copy(api.apikey)"></span>
							</td>
							<td class="left content">
								{{ api.description }}
								<div class="buttons">
                                    <a href="#" @click.prevent="toggle(api.idx)" :class="{on: api.disabled=='N'}">
                                        <i :class="{['xi-toggle-on']: api.disabled == 'N', ['xi-toggle-off']: api.disabled == 'Y'}"></i>
                                    </a>
									<a href="#" @click.prevent="remove(api.idx)"><i class="xi-trash"></i></a>
								</div>						
							</td>
						</tr>
						<tr v-if="list.length == 0 || loading">
							<td colspan="4"  :class="{nodata: true, loading: this.loading}"><i class="xi-info xi-1x"></i> 생성된 키가 없습니다.</td>
						</tr>
					</tbody>
				</table>
				<div class="bottom">
					<div class="page_block" v-html="paginator"></div>
					<div class="button_block">
						<span class="button"><input type="button" value="생성" @click="open"></span>
					</div>
				</div>

				<form class="apiForm modal" @submit.stop="generate">
					<div class="dimmed"></div>
					<div class="form">
						<h2>
							<i class="xi-link"></i>&nbsp;&nbsp;API키 등록
						</h2>
						<table class="table tableVertical">
							<colgroup>
								<col style="width: 50px">
							</colgroup>
							<tbody>
								<tr>
									<th>일시 중지</th>
									<td><input type="checkbox" v-model="disabled"> 해당 API를 중지상태로 변경합니다.</td>
								</tr>
								<tr>
									<th>API 사용 용도</th>
									<td><input type="text" v-model="description" class="input_text input_full"></td>
								</tr>
							</tbody>
						</table>
						<div class="bottom">
							<div class="button_block">
								<span class="button ok"><input type="button" value="등록" @click="generate"></span>
								<span class="button"><input type="button" value="닫기" @click="close"></span>
							</div>
						</div>
					</div>
				</form>

			</div>
			<br>

			<mypage_admin v-if="is_admin"></mypage_admin>
		</div>
	</div>
</template>

<script>
export default {
	data: function() {
		return {
			is_admin: false,
			darkmode: (window.localStorage.getItem('site-darkmode') == 'Y') ? 'Y' : 'N',
			list: [],
			paginator: '',
			description: '',
			disabled: false,
			loading: false
		}
	},
	watch: {
		darkmode: function(v) {
			window.localStorage.setItem('site-darkmode', v);
			if (v == 'Y') {
				document.querySelector('body').classList.add('darkmode');
			} else {
				document.querySelector('body').classList.remove('darkmode');
			}
			floating('설정이 변경되었습니다.');
		}
	},
	methods: {
		reload: function() {
			api('/api/site/keys')
				.then((ret) => {
					if (ret.status == 'success') {
						this.list = ret.list;
						this.is_admin = ret.is_admin;
					}
					this.loading = false;
				});
		},
		signout: function() {
			api('/api/staff/signout')
				.then(() => {
                    window.sessionStorage.setItem('token', '');
					location.reload();
				});
		},
		changeProject: function() {
            window.localStorage.setItem('current_project_idx', '');
			this.$router.push({
				path: '/project/select'
			});
		},
		copy: function(key)
		{
			navigator.clipboard.writeText(key).then(() => {
				floating('API Key가 복사되었습니다.');
			});
		},
		open: function()
		{
			$('.apiForm').css('display', 'flex').fadeIn();
		},
		close: function() {
			$('.apiForm').fadeOut();
		},
		generate: function()
		{
			api('/api/site/genarate', 'get', {'description': this.description, 'disabled': this.disabled})
				.then((res) => {
					this.disabled = false;
					this.description = '';
					if (res.status == 'success') {
						this.reload();
						this.close();
					}
				});
		},
		remove: function(idx)
		{
			if (!confirm('API키를 삭제하시겠습니까?')) {
				return false;
			}
			api('/api/site/remove/'+idx)
				.then((res) => {
					if (res.status == 'success') {
						this.reload();
					}
				});
		},
        toggle: function(idx) {
            api('/api/site/toggle/'+idx)
                .then((res) => {
                    if (res.status == 'success') {
                        this.reload();
                    }
                });
        }
	},
	mounted: function() {
		this.reload();
	}, 
	updated: function() {
		const nodata = this.$el.querySelector('.nodata');
		if (nodata) nodata.classList.remove('loading');
	},
	components: {
		mypage_summary: Vue.defineAsyncComponent(() => loadModule('/resource/view/mypage/summary.vue', options)),
		mypage_admin: Vue.defineAsyncComponent(() => loadModule('/resource/view/mypage/admin.vue', options))
	}
}
</script>