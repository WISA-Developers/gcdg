<template>
	<div id="project" class="layoutHorizontal">
		<form class="leftArea flexible" id="searchForm">

			<fieldset>
				<legend>진행 상태</legend>
				<ul>
					<li v-for="(value, key) in define_config.project_stat">
						<label>
							<input type="checkbox" :value=key v-model="search.status" @click.ctrl="checkboxRadio('status', $event)">
							{{ value }}
						</label>
					</li>
				</ul>
			</fieldset>

			<fieldset>
				<legend>담당자</legend>
				<staffs_search 
					name="role" 
					source="staff/list" 
					:search.sync="search.role" 
					@get-search="getSearch"
				></staffs_search>
			</fieldset>

			<fieldset>
				<legend>프로젝트명</legend>
				<div class="m_half">
					<input type="text" @input="keyinput" :value="search.title" name="title" class="input_text input_full" placeholder="제목">
				</div>
			</fieldset>

			<fieldset class="m_half">
				<legend>등록일</legend>
				<div class="cal">
					<datepicker name="registerd_s" @get-search="getSearch" :date="search.registerd_s"></datepicker>
					<datepicker name="registerd_e" @get-search="getSearch" :date="search.registerd_e"></datepicker>
				</div>
			</fieldset>

			<fieldset class="m_half">
				<legend>수정일</legend>
				<div class="cal">
					<datepicker name="modified_s" @get-search="getSearch" :date="search.modified_s"></datepicker>
					<datepicker name="modified_e" @get-search="getSearch" :date="search.modified_e"></datepicker>
				</div>
			</fieldset>
		</form>

		<div class="contentArea">
			<h2 class="title"><i class="xi-view-list xi-1x"></i> 프로젝트 목록</h2>
			<table class="table tableHorizontal flexible">
				<colgroup>
					<col style="width:110px;">
					<col style="width:80px">
					<col>
					<col>
					<col>
				</colgroup>
				<thead>
				<tr>
					<th>생성일시</th>
					<th>상태</th>
					<th>프로젝트명</th>
					<th>관리자</th>
					<th>참여자</th>
				</tr>
				</thead>
				<tbody>
				<tr v-if="!this.loading" v-for="data in list">
					<td class="date">{{ data.registerd }}</td>
					<td>{{ data.status }}</td>
					<td class="left">
						<router-link :to="'/project/create/'+data.idx">
							<span v-if="data.repository" class="xi-network-server attr_icon"></span>
							{{ data.project_name }}
						</router-link>
					</td>
					<td class="left pc">
						<div class="issue_list_staffs">
							<li v-for="staff in data.admin">
								<template v-if="staff">{{ staff.name }}</template>
							</li>
						</div>
					</td>
					<td class="left pc">
						<div class="issue_list_staffs">
							<li v-for="staff in data.staffs">
                                <template v-if="staff">{{ staff.name }}</template>
							</li>
						</div>					
					</td>
				</tr>
				<tr v-if="list.length == 0 || loading">
					<td colspan="5"  :class="{nodata: true, loading: this.loading}"><i class="xi-info xi-1x"></i> 검색된 내역이 없습니다.</td>
				</tr>
				</tbody>
			</table>
			<div class="bottom">
				<div class="page_block" v-html="paginator"></div>
				<div class="button_block">
					<span class="button"><input type="button" value="프로젝트 등록" @click="create"></span>
				</div>
			</div>
		</div>
	</div>
</template>
<script type="module">
export default {
	inject: ['define_config', 'staff_snapshot'],
	setup: function() {
		return Vue.reactive({
			search: {
				page: 1,
				status: [],
				role: [],
				title: null,
				registerd_s: null,
				registerd_e: null,
				modified_s: null,
				modified_e: null
			},
			list: [],
			page: 1,
			paginator: null,
			loading: false
		})
	},
	watch: {
		search: {
			deep: true,
			handler: function() {
				this.$router.push({
					path: this.$route.path,
					query: proxyToQuery(this.search)
				});
			 }
		}
	},
	methods: {
		hashchanged: function() {
			if (this.$router.currentRoute.value.path != '/project') return false;

			clearTimeout(window.search_interval);
			window.search_interval = setTimeout(() => {
				this.search = urlToParam(this.search);
				this.reload();
			}, 200);
		},
		keyinput: function(e) {
			const input = e.target;
			const name = input.getAttribute('name');
			this.search[name] = input.value;
		},
        checkboxRadio: function(k, e) {
            const cb = e.target;
            if (cb.checked == true) {
                this.search[k] = [cb.value];
            }
        },
		reload: function() {
			this.loading = true;
			api('/api/project/index', 'get', proxyToQuery(this.search)).then((ret) => {
				if (ret.status == 'success') {
					this.list = ret.data;
					this.paginator = ret.paginator;
				}
				this.loading = false;
			});
		},
		getSearch: function(k, v) {
			this.search[k] = v;
		},
		create: function() {
			this.$router.push({
				path: '/project/create'
			});
		}
	},
	mounted: function() {
		this.search = urlToParam(this.search);
		this.reload();
	},
	components: {
		datepicker,
		'staffs_search': Vue.defineAsyncComponent(() => loadModule('/resource/view/common/staffs_search.vue', options)),
	}

}
</script>