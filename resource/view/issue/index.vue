<template>
	<div id="issue" class="layoutHorizontal">
		<form class="leftArea flexible" id="searchForm">
			<fieldset>
				<legend>업무 종류</legend>
				<ul>
					<li v-for="(value, key) in define_config.work_type">
						<label>
							<input type="checkbox" :value=key v-model="search.work_type" @click.ctrl="checkboxRadio('work_type', $event)"> 
							{{ value }}
						</label>
					</li>
				</ul>
			</fieldset>
			
			<fieldset>
				<legend>진행 상태</legend>
				<ul>
					<li v-for="(value, key) in define_config.issue_stat">
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
				<legend>내용</legend>
				<div class="m_half">
					<input type="text" @input="keyinput" :value="search.title" name="title" class="input_text input_full" placeholder="제목">
					<input type="text" @input="keyinput" :value="search.content" name="content" class="input_text input_full" placeholder="본문">
				</div>
			</fieldset>

			<fieldset>
				<legend>중요도</legend>
				<div class="input_importance checkable">
					<label class="xi-star-o xi-2x"><input type="radio" v-model="search.importance" value="1" @click.stop="importance"></label>
					<label class="xi-star-o xi-2x"><input type="radio" v-model="search.importance" value="2" @click.stop="importance"></label>
					<label class="xi-star-o xi-2x"><input type="radio" v-model="search.importance" value="3" @click.stop="importance"></label>
					<label class="xi-star-o xi-2x"><input type="radio" v-model="search.importance" value="4" @click.stop="importance"></label>
					<label class="xi-star-o xi-2x"><input type="radio" v-model="search.importance" value="5" @click.stop="importance"></label>
				</div>
			</fieldset>

			<fieldset>
				<legend>디바이스</legend>
				<ul>
					<li v-for="device in define_config.devices">
						<label>
							<input type="checkbox" :value="device" v-model="search.device" @click.ctrl="checkboxRadio('device', $event)"> {{ device }}
						</label>
					</li>
				</ul>
			</fieldset>

			<fieldset class="m_half">
				<legend>일정</legend>
				<div class="cal">
					<datepicker name="plan_s" @get-search="getSearch" :date="search.plan_s"></datepicker>
					<datepicker name="plan_e" @get-search="getSearch" :date="search.plan_e"></datepicker>
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
			<h2 class="title"><i class="xi-view-list xi-1x"></i> 이슈 목록</h2>
			<table class="table tableHorizontal flexible">
				<colgroup>
						<col style="width:110px;">
						<col style="width:70px">
						<col>
						<col style="width:250px">
				</colgroup>
				<thead>
				<tr>
					<th>작성일시</th>
					<th>처리상태</th>
					<th>제목</th>
					<th>담당자</th>
				</tr>
				</thead>
				<tbody>
				<tr v-if="!this.loading" v-for="data in list">
					<td class="date">
						{{ data.registerd }}
					</td>
					<td>{{ data.status.value }}</td>
					<td class="left" style="text-overflow: ellipsis; white-space: nowrap; overflow: hidden;">
						<a href="#" @click.stop.prevent="view(data.idx)">
							<span :class="{'xi-star': true, ['importance_'+data.importance]: true, 'attr_icon': true}">{{ data.importance }}</span>

							<i v-if="data.work_type=='N'" :class="{'xi-new': true, 'attr_icon': true, 'now_select': search.work_type.includes('N')}"></i>
							<i v-if="data.work_type=='E'" :class="{'xi-plus-circle-o': true, 'attr_icon': true, 'now_select': search.work_type.includes('E')}"></i>
							<i v-if="data.work_type=='B'" :class="{'xi-bug': true, 'attr_icon': true, 'now_select': search.work_type.includes('B')}"></i>

							<i v-if="data.wep_idx" class="xi-share attr_icon"></i>
							<i v-if="data.device.includes('PC')" :class="{'xi-desktop': true, 'attr_icon': true, 'now_select': search.device.includes('PC')}"></i>
							<i v-if="data.device.includes('android')" :class="{'xi-android': true, 'attr_icon': true, 'now_select': search.device.includes('android')}"></i>
							<i v-if="data.device.includes('IOS')" :class="{'xi-apple': true, 'attr_icon': true, 'now_select': search.device.includes('IOS')}"></i>
							<i v-if="data.device.includes('APP')" :class="{'xi-apps': true, 'attr_icon': true, 'now_select': search.device.includes('APP')}"></i>
							<span :class="{['issue_status_'+data.status.code]:true}">{{ data.title }}</span>
							
						</a>
					</td>
					<td class="pc left">
						<div class="issue_list_staffs">
							<li v-for="staff in data.staffs">
								{{ staff.name }}
							</li>
						</div>
					</td>
				</tr>
				<tr v-if="list.length == 0 || loading">
					<td colspan="4"  :class="{nodata: true, loading: this.loading}"><i class="xi-info xi-1x"></i> 검색된 내역이 없습니다.</td>
				</tr>
				</tbody>
			</table>
			<div class="bottom">
				<div class="page_block" v-html="paginator"></div>
				<div class="button_block">
					<span class="button"><input type="button" value="이슈 등록" @click="ticket"></span>
				</div>
			</div>
								
		</div>
	</div>
</template>

<script>
export default {	
	inject: ['define_config', 'staff_snapshot'],
	setup: function() {
		return Vue.reactive({
			search: {
		        page: 1,
				work_type: [],
				status: [],
				role: [],
				title: null,
				content: null,
				importance: 0,
				device: [],
				plan_s: null,
				plan_e: null,
				registerd_s: null,
				registerd_e: null,
				modified_s: null,
				modified_e: null
			},
			list: [],
			page: 1,
			paginator: null,
			loading: true
		})
	},
	watch: {
		search: {
			deep: true,
			handler: function() {
				// 검색 시 페이지 초기화
				if (this.page == this.search.page) this.search.page = 1;
				this.page = this.search.page;

				// 이동
                this.$router.push({
                    path: this.$route.path,
                    query: proxyToQuery(this.search)
                });
			 }
		},
		'search.importance': function(n, o) {
			let stars = document.querySelectorAll('.input_importance > label');
			let point = (n == o) ? -1 : n-1;
			stars.forEach(function(o, idx) {
				if (point >= idx) {
					o.classList.add('xi-star')
					o.classList.remove('xi-star-o');
				} else {
					o.classList.remove('xi-star')
					o.classList.add('xi-star-o');
				}
			});
		}
	},
	methods: {
		hashchanged: function() {
			if (this.$router.currentRoute.value.path != '/issue') return false;

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
			api('/api/issue/index', 'get', proxyToQuery(this.search)).then((ret) => {
				if (ret.status == 'success') {
					this.list = ret.data;
					this.paginator = ret.paginator;
				}
				this.loading = false;
			});
		},
		importance: function(e) { // 동일 중요도 클릭 시 전체 해제
			if (this.search.importance == parseInt(e.target.value)) {
				this.search.importance = 0;
			}
		},
		getSearch: function(k, v) {
			this.search[k] = v;
		},
		view: function(idx) {
			this.$router.push({
				path: '/issue/view/'+idx
			});
		},
		ticket: function() {
			this.$router.push({
				path: '/issue/ticket'
			});
		}
	},
	mounted: function() {
		this.search = urlToParam(this.search);
		this.reload();
	},
	components: {
		datepicker,
		staffs_search: Vue.defineAsyncComponent(() => loadModule('/resource/view/common/staffs_search.vue', options)),
	}
}
</script>