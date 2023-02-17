<template>
	<div id="issueTicket" class="layoutHorizontal">
		<div class="leftArea">
			<ul class="bookmark_list flexible">
				<li @click="goBookmark(0)">기본 정보</li>
				<li @click="goBookmark(1)">이슈 진행도</li>
				<li @click="goBookmark(2)">코멘트 ({{ child_data.component_comment_count }})</li>
				<li @click="goBookmark(3)">참고자료 및 산출물 ({{ child_data.component_file_count }})</li>
				<li @click="goBookmark(4)">관련 이슈 ({{ child_data.comoponent_chain_count }})</li>
			</ul>
		</div>

		<div class="contentArea">
			<!-- 기본 정보 -->
			<div class="bookmark" section="1">
				<h2 class="title">
					<i v-if="data.work_type=='N'" class="xi-new xi-1x"></i>
					<i v-if="data.work_type=='E'" class="xi-plus-circle-o xi-1x"></i>
					<i v-if="data.work_type=='B'" class="xi-bug xi-1x"></i>
					{{ data.title }}
					<i v-if="data.wep_idx" class="xi-share"> <a href="#" @click.stop.prevent="goWep(data.wep_idx)">from wep</a></i>
				</h2>
				<table class="table tableVertical">			
					<colgroup>
						<col>
						<col style="width: 100%">
					</colgroup>
					<tbody>
					<tr>
						<th>상태</th>
						<td>
							<label v-for="(value, key) in define_config.issue_stat">
								<input type="radio" name="status" v-model="data.status" :value="key" :checked="data.status==key" @click="setStatus"> {{ value }}
							</label>
						</td>
					</tr>
					<tr v-if="(data.plan_s && data.plan_e)">
						<th>일정</th>
						<td>
                            <div class="cal">
                                <datepicker v-if="data.plan_s" name="plan_s" :date="data.plan_s" @get-search="changePlan"></datepicker>
                                <datepicker v-if="data.plan_e" name="plan_e" :date="data.plan_e" @get-search="changePlan"></datepicker>
                            </div>
						</td>
					</tr>
					<tr>
						<th>작성자</th>
						<td>
							<ul class="staffs_role">
								<li v-for="staff in data.creater">
									<img :src="'/api/staff/portrait/'+staff.idx">
									{{ staff.name }} &lt;{{ staff.group_name }}&gt;
								</li>
							</ul>
						</td>
					</tr>
					<tr v-if="data.planner">
						<th>기획</th>
						<td>
							<ul class="staffs_role">
								<li v-for="staff in data.planner">
									<img :src="'/api/staff/portrait/'+staff.idx">
									{{ staff.name }} &lt;{{ staff.group_name }}&gt;
								</li>
							</ul>
						</td>
					</tr>
					<tr v-if="data.designer">
						<th>디자인</th>
						<td>
							<ul class="staffs_role">
								<li v-for="staff in data.designer">
									<img :src="'/api/staff/portrait/'+staff.idx">
									{{ staff.name }} &lt;{{ staff.group_name }}&gt;
								</li>
							</ul>
						</td>
					</tr>
					<tr v-if="data.publisher">
						<th>퍼블리싱</th>
						<td>
							<ul class="staffs_role">
								<li v-for="staff in data.publisher">
									<img :src="'/api/staff/portrait/'+staff.idx">
									{{ staff.name }} &lt;{{ staff.group_name }}&gt;
								</li>
							</ul>
						</td>
					</tr>
					<tr v-if="data.developer">
						<th>개발</th>
						<td>
							<ul class="staffs_role">
								<li v-for="staff in data.developer">
									<img :src="'/api/staff/portrait/'+staff.idx">
									{{ staff.name }} &lt;{{ staff.group_name }}&gt;
                                    <i v-if="read.includes(staff.idx)" class="xi-check" style="color: #ff1111"></i>
								</li>
							</ul>
						</td>
					</tr>
					<tr v-if="data.tester">
						<th>검수</th>
						<td>
							<ul class="staffs_role">
								<li v-for="staff in data.tester">
									<img :src="'/api/staff/portrait/'+staff.idx">
									{{ staff.name }} &lt;{{ staff.group_name }}&gt;
                                    <i v-if="read.includes(staff.idx)" class="xi-check" style="color: #ff1111"></i>
								</li>
							</ul>
						</td>
					</tr>
					<tr v-if="data.referer">
						<th>참조자</th>
						<td>
							<ul class="staffs_role">
								<li v-for="staff in data.referer">
									<img :src="'/api/staff/portrait/'+staff.idx">
									{{ staff.name }} &lt;{{ staff.group_name }}&gt;
                                    <i v-if="read.includes(staff.idx)" class="xi-check" style="color: #ff1111"></i>
								</li>
							</ul>
						</td>
					</tr>
					<tr v-if="data.device.length > 0">
						<th>디바이스</th>
						<td>
							<ul class="staffs_role">
								<li v-for="device in data.device">{{ device }}</li>
                                <i v-if="read.includes(staff.idx)" class="xi-check" style="color: #ff1111"></i>
							</ul>
						</td>
					</tr>
					<tr>
						<th>중요도</th>
						<td>
							<div class="input_importance">
								<label class="xi-star-o xi-2x"></label>
								<label class="xi-star-o xi-2x"></label>
								<label class="xi-star-o xi-2x"></label>
								<label class="xi-star-o xi-2x"></label>
								<label class="xi-star-o xi-2x"></label>
							</div>
						</td>
					</tr>
					<tr>
						<th>저장소 리비전</th>
						<td>
							<ul class="revision">
								<li v-for="rev in data.repository">
									<router-link :to="'/repository/'+rev.rev">
									<strong>{{ rev.rev_v }}</strong> {{ rev.message }}
									</router-link>
								</li>
							</ul>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="padding: 0 0 20px 0">
							<div id="viewer"></div>
						</td>
					</tr>
					</tbody>
				</table>
				<div class="bottom">
					<div class="button_block">
						<span class="button ok"><input type="button" value="수정" @click="goEdit"></span>
						<span class="button"><input type="button" value="목록" @click="goList"></span>
						<span class="button cancel"><input type="button" value="삭제" @click="remove"></span>
					</div>
				</div>
			</div>

			<!-- 이슈 진행도 -->
			<div class="bookmark" section="2">
				<h2 class="title"><i class="xi-paper xi-1x"></i> 이슈 진행도</h2>
				<div class="description">
					<i class="xi-keyboard-o"></i> ctrl+클릭 시 예정 일정, 
					<i class="xi-keyboard-o"></i> alt+클릭 시 실행 일정이 등록됩니다.
				</div>
				<div class="project_plan" v-if="(data.plan_s && data.plan_e && data.staffs_count > 0)">
					<table class="table tableHorizontal excelstyle">
						<colgroup>
							<col style="width:100px">
							<col style="width:100px">
						</colgroup>
						<thead>
						<tr class="plan_dates_label">
							<th class="sticky">분류</th>
							<th class="sticky">담당자</th>
							<th v-for="cal in plan.dateset">{{ cal.month }}/{{ cal.day }}</th>
							<th class="blank"></th>
						</tr>
						</thead>
						<tbody>
						<tr v-for="staff in data.planner">
							<th>기획</th>
							<th>{{ staff.name }}</th>
							<td 
								v-for="cal in plan.dateset" 
								@click.stop="setDayPlan"
								:data-role-idx="staff.role_idx" 
								:data-date="cal.date"
								:class="{'friday':(cal.week==5)}"
							>
								<div :class="{'plan_bar': true, 's1': hasPlan(staff.role_idx, cal.date, 1)}"></div>
								<div :class="{'plan_bar': true, 's2': hasPlan(staff.role_idx, cal.date, 2)}"></div>										
							</td>
							<td class="blank">
							</td>
						</tr>
						<tr v-for="staff in data.designer">
							<th>디자인</th>
							<th>{{ staff.name }}</th>
							<td 
								v-for="cal in plan.dateset" 
								@click.stop="setDayPlan"
								:data-role-idx="staff.role_idx" 
								:data-date="cal.date"
								:class="{'friday':cal.week==5}"
							>
								<div :class="{'plan_bar': true, 's1': hasPlan(staff.role_idx, cal.date, 1)}"></div>
								<div :class="{'plan_bar': true, 's2': hasPlan(staff.role_idx, cal.date, 2)}"></div>										
							</td>
							<td class="blank"></td>
						</tr>
						<tr v-for="staff in data.publisher">
							<th>퍼블리싱</th>
							<th>{{ staff.name }}</th>
							<td 
								v-for="cal in plan.dateset" 
								@click.stop="setDayPlan"
								:data-role-idx="staff.role_idx" 
								:data-date="cal.date"
								:class="{'friday':cal.week==5}"
							>
								<div :class="{'plan_bar': true, 's1': hasPlan(staff.role_idx, cal.date, 1)}"></div>
								<div :class="{'plan_bar': true, 's2': hasPlan(staff.role_idx, cal.date, 2)}"></div>										
							</td>
							<td class="blank"></td>
						</tr>
						<tr v-for="staff in data.developer">
							<th>개발</th>
							<th>{{ staff.name }}</th>
							<td 
								v-for="cal in plan.dateset" 
								@click.stop="setDayPlan"
								:data-role-idx="staff.role_idx" 
								:data-date="cal.date"
								:class="{'friday':cal.week==5}"
							>
								<div :class="{'plan_bar': true, 's1': hasPlan(staff.role_idx, cal.date, 1)}"></div>
								<div :class="{'plan_bar': true, 's2': hasPlan(staff.role_idx, cal.date, 2)}"></div>										
							</td>
							<td class="blank"></td>
						</tr>
						<tr v-for="staff in data.tester">
							<th>검수</th>
							<th>{{ staff.name }}</th>
							<td 
								v-for="cal in plan.dateset" 
								@click.stop="setDayPlan"
								:data-role-idx="staff.role_idx" 
								:data-date="cal.date"
								:class="{'friday':cal.week==5}"
							>
								<div :class="{'plan_bar': true, 's1': hasPlan(staff.role_idx, cal.date, 1)}"></div>
								<div :class="{'plan_bar': true, 's2': hasPlan(staff.role_idx, cal.date, 2)}"></div>										
							</td>
							<td class="blank"></td>
						</tr>
						</tbody>
					</table>
				</div>
				<div v-if="(!data.plan_s || !data.plan_e || data.staffs_count == 0)" class="noplan">
					시작/끝 일정과 담당자를 선택해야 프로젝트 진행도를 입력할 수 있습니다.
				</div>
			</div>

			<!-- 코멘트 -->
			<div class="bookmark" section="3">
				<h2 class="title"><i class="xi-forum"></i> 코멘트</h2>
				<issue_comment v-if="data.hash" ref="ref_comments" @get-data="getData" :data="data"></issue_comment>
			</div>

			<!-- 참고자료 및 산출물 -->
			<div class="bookmark" section="4">
				<h2 class="title"><i class="xi-file"></i> 참고자료 및 산출물</h2>
				<issue_files v-if="data.hash" ref="ref_files" @get-data="getData" :data="data"></issue_files>
			</div>

			<!-- 이슈 체인 -->
			<div class="bookmark" section="5">
				<h2 class="title"><i class="xi-link"></i> 이슈 체인</h2>
				<issue_chain v-if="data.hash" ref="ref_chains" @get-data="getData" :data="data"></issue_chain>
			</div>
		</div>
	</div>
</template>

<script type="module">
const { Editor } = toastui;
const { codeSyntaxHighlight } = Editor.plugin;

export default {
	inject: ['define_config'],
	setup: function() {
		return Vue.reactive({
            idx : null,
			data: {
				device: []
			},
            read: [],
			plan: {},
			child: {
				c: 1,
				f: 1,
				i: 1,
				l: null
			},
			child_data: {
				component_comment_count: '-',
				component_file_count: '-',
				comoponent_chain_count: '-'
			}
		})
	},
	watch: {
		'child.c': function() {
			this.$refs.ref_comments.hashchanged();
		},
		'child.f': function() {
			this.$refs.ref_files.hashchanged();
		},
		'child.i': function() {
			this.$refs.ref_chains.hashchanged();
		},
        idx: function(n, o) {
            if (!o || n == o) return;

            this.reload();
            this.$refs.ref_comments.hashchanged();
            this.$refs.ref_files.hashchanged();
            this.$refs.ref_chains.hashchanged();
            this.goBookmark(0);
        }
	},
	methods: {
		hashchanged: function() {
			const uri = new URL(location.href);
			const suri = new URL(uri.origin+'?'+uri.hash.split('?').at(1));
			this.child.c = suri.searchParams.get('c') || 1;
			this.child.f = suri.searchParams.get('f') || 1;
			this.child.i = suri.searchParams.get('i') || 1;

			// 스크롤 이동
			this.scrollToComponent();

            if (this.$route.params.idx) {
                this.idx = this.$route.params.idx;
            }
		},
		reload: function() {
			api('/api/issue/get/'+this.idx+'?scope=plan')
				.then((ret) => {
					if (ret.status == 'success') {
						this.data = ret.data;
                        this.read = ret.read;

						// 뷰어
						toastui.Editor.factory({
							el: document.querySelector('#viewer'),
							viewer: true,
							initialValue: this.data.content.toString(),
							plugins: [codeSyntaxHighlight],
							theme: (window.localStorage.getItem('site-darkmode') == 'Y') ? 'dark' : 'basic'
						});

						// 별점
						this.importance();

						// 이슈 진행도
                        this.drawPlan();
				} else {
					this.goList();
				}
				removeLoading();
			});
		},
		importance: function() {
			const importance = this.data.importance;
			$('.input_importance > label').each(function(idx) {
				if (idx < importance) {
					this.classList.add('xi-star');
					this.classList.remove('xi-star-o');
				}
			})
		},
		goBookmark(index) {
			const bookmark = $('.bookmark').eq(index).offset();
			if (bookmark) {
				const height = bookmark.top-10;
				if ($('.layoutHorizontal').css('display') == 'grid') {
					$('.contentArea').animate({scrollTop: height+$('.contentArea').scrollTop()-$('.contentArea').offset().top});
				} else {
					$('html, body').animate({scrollTop: height});
				}
			}
		},
        setStatus: function(o) {
            if (o.target != this.data.status) {
                api('/api/issue/setStatus/' + this.data.idx + '/' + o.target.value)
                    .then((ret) => {
                        floating(ret.message);
                    });
            }
        },
        drawPlan: function() {
            this.plan.dateset = [];
            if (this.data.plan) {
                // 시작일~종료일의 일 수
                this.plan = this.data.plan;
                if (this.plan.total_days) {
                    // 기간 내 전체 날짜 목록
                    this.plan.dateset = [];
                    for (let i = 0; i < this.plan.total_days; i++) {
                        let today = new Date(this.data.plan_s);
                        today = new Date(today.setDate(today.getDate()+i));
                        let week = today.getDay();
                        if (week == 0 || week == 6) continue;

                        let year = today.getFullYear();
                        let month = (today.getMonth()+1).toString().padStart(2, '0');
                        let day = today.getDate().toString().padStart(2, '0');

                        this.plan.dateset.push({
                            year: year,
                            month: month,
                            day: day,
                            week: week,
                            date: year+'-'+month+'-'+day
                        });
                    }
                }
            }
        },
		setDayPlan(e) {
			let schedule_type = 0;
			if (e.ctrlKey) schedule_type = 1;
			else if (e.altKey) schedule_type = 2;
			else return false;

			let self = this;
			let o = $(e.currentTarget);
			let role_idx = o.data('role-idx')
			let date = o.data('date');
			api('/api/issue/setDayPlan', 'get', {role_idx: role_idx, date: date, schedule_type: schedule_type})
				.then((ret) => {
					if (ret.status == 'success') {
						if (!self.plan.schedule) self.plan.schedule = [];
						if (!self.plan.schedule[date]) self.plan.schedule[date] = [];
						if (!self.plan.schedule[date][role_idx]) self.plan.schedule[date][role_idx] = 0;
						self.plan.schedule[date][role_idx] += ret.change_value;
					}
				});
		},
		hasPlan: function(role_idx, cal_date, schedule_type) {
			if (!this.plan) return false;
			if (!this.plan.schedule) return false;
			if (!this.plan.schedule[cal_date]) return false;
			if (!this.plan.schedule[cal_date][role_idx]) return false;

			let s = this.plan.schedule[cal_date][role_idx]
			if (s == schedule_type || s == 3) {
				return true;
			}
			return false;
		},
		goList: function() {
            if (this.previous_url) location.href = this.previous_url;
            else {
                this.$router.push({
                    path: '/issue'
                })
            }
		},
		goEdit: function() {
			this.$router.push({
				path: '/issue/ticket/'+this.data.idx
			});
		},
		remove: function() {
			if (!window.confirm('삭제한 데이터는 복구할 수 없습니다.\n이슈를 삭제하시겠습니까?')) {
				return false;
			}

			printLoading();
			api('/api/issue/remove/'+this.data.idx)
				.then((ret) => {
					removeLoading();
					if (ret.result == 'success') {
						this.goList();
					}
				});
		},
		getData: function(k, v) {
			this.child_data[k] = v;
		},
		scrollToComponent: function() {
			const url = new URL(location.href);
			const params = (new URLSearchParams(url.hash.substring(1)));
			this.child.l = params.get('l');
			switch(this.child.l) {
				case 'c' :
					this.goBookmark(2);
					break;
				case 'f' :
					this.goBookmark(3);
					break;
				case 'i' :
					this.goBookmark(4);
					break;
			}
			this.data.scrollNext = params.get('l');
		},
        changePlan: function(n, v) {
            api('/api/issue/setPlan/'+this.data.idx+'?n='+n+'&v='+v)
                .then((ret) => {
                    floating(ret.message);
                    this.reload();
                });
        },
		goWep: function(idx)
		{
			window.open('https://wep.wisa.co.kr/customer/detail?customer_idx='+idx)
		}
	},
	beforeMount: function() {
        // 이전 리스트 주소
        const last_pathname = (new URL('https://localhost'+this.$router.options.history.state.back)).pathname;
        this.previous_url = (last_pathname == '/issue') ? '/#'+this.$router.options.history.state.back : null;

        // 현재 issue 번호
        this.idx = this.idx = this.$route.params.idx;

		this.reload();
		printLoading();
	},
	updated: function() {
		if (!this.l) {
			this.scrollToComponent();
		}
	},
    components: {
		'issue_files': Vue.defineAsyncComponent(() => loadModule('/resource/view/issue/issue_files.vue', options)),
		'issue_comment': Vue.defineAsyncComponent(() => loadModule('/resource/view/issue/issue_comment.vue', options)),
		'issue_chain': Vue.defineAsyncComponent(() => loadModule('/resource/view/issue/issue_chain.vue', options)),
        datepicker
	}
}
</script>