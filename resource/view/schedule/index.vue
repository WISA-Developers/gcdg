<template>
	<div id="schedule" class="layoutHorizontal">
		<form class="leftArea flexible" id="searchForm">
			<fieldset>
				<legend>프로젝트</legend>
				<ul class="long">
					<li><label><input type="radio" value="" v-model="search.project_range"> {{ me.current_project_name }}</label></li>
                    <li><label><input type="radio" value="ALL" v-model="search.project_range"> 전체</label></li>
				</ul>
			</fieldset>

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
                <legend>그룹</legend>
                <ul>
                    <li>
                        <label>
                            <input type="radio" value="" v-model="search.group_idx">
                            전체
                        </label>
                    </li>
                    <li v-for="group in group_info">
                        <label>
                            <input type="radio" :value=group.idx v-model="search.group_idx">
                            {{ group.name }}
                        </label>
                    </li>
                </ul>
            </fieldset>

			<fieldset>
				<legend>내용</legend>
				<div class="m_half">
					<input type="text" @input="keyinput" :value="search.title" name="title" class="input_text input_full" placeholder="제목">
					<input type="text" @input="keyinput" :value="search.content" name="text" class="input_text input_full" placeholder="본문">
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
		</form>

		<div :class="{contentArea: true, vertical: vertical=='Y'}">
			<h2 class="title" style="position: relative; text-align: center; height: 50px; background-color: var(--table-th-background-color)">
				<a href="#" class="xi-angle-left xi-x" @click.prevent="setYm(-1)"></a>
				{{ ym }}
				<a href="#" class="xi-angle-right xi-x" @click.prevent="setYm(+1)"></a>
				<div class="buttons">
					<a href="#" @click.stop.prevent="setDesignMode('N')" :class="{on: vertical!='Y'}"><i class="xi-border-all"></i></a>
					<a href="#" @click.stop.prevent="setDesignMode('Y')" :class="{on: vertical=='Y'}"><i class="xi-border-horizontal"></i></a>
				</div>
			</h2>

			<ul class="calender-week">
				<li>월</li>
				<li>화</li>
				<li>수</li>
				<li>목</li>
				<li>금</li>
			</ul>

			<ul class="calender">
				<li v-for="data in calday">
					<div v-if="data.is_date || vertical!='Y'">
						<div class="day">{{ data.day }}</div>
						<ul class="plans">
							<li
								v-for="plan in getPlan(data)" 
								:class="{['issue_color_'+plan.issue_offset]: true, has_plan: plan.issue_idx > 0}"
								@click="openIsuue(plan.issue_idx)"
							>
								{{ plan.title }}&nbsp;
							</li>
						</ul>
					</div>
				</li>
			</ul>		
		</div>
	</div>
</template>

<script type="module">
export default {
	inject: ['define_config', 'staff_snapshot'],
    props: {
        'me': Array
    },
	setup: function() {
		const to_month = new Date().toISOString().substr(0, 7);
		return {
			to_month,
			search: Vue.reactive({
				project_range: '',
				ym: to_month,
				work_type: [],
				status: [],
				role: [],
                group_idx: '',
				title: '',
				content: '',
				importance: 0
			})
		}
	},
	data: function() {
		return {
			ym: null,
			lastday: null,
			calstart: null,
			calend: null,
			issue_count: null,
			calday: null,
			plans: null,
            group_info: [],
			vertical: window.localStorage.getItem('calendar-vertical'),
		}
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
			if (this.$router.currentRoute.value.path != '/schedule') return false;

			if (window.search_interval) {
				clearTimeout(window.search_interval);
			}
			window.search_interval = setTimeout(() => {
				this.search = urlToParam(this.search);
				this.reload();
			}, 100);
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
			api('/api/schedule/list', 'get', proxyToQuery(this.search))
				.then((ret) => {
					this.lastday = ret.lastday;
					this.calstart = ret.calstart;
					this.calend = ret.calend;
					this.issue_count = ret.issue_count;
					this.plans = ret.plans;
                    this.group_info = ret.group_info;
					this.calday = this.getCalday(ret.ym);
					this.ym = ret.ym;
			
					if (this.search.ym || ret.ym != this.to_month) {
						this.search.ym = ret.ym;
					}

					removeLoading();
				});
		},
		getCalday: function(ym) {
			let data = [];
			let week_tmp = [];
			for(let idx = this.calstart; idx <= this.calend; idx++) {
				let calday = new Date(ym+'-01');
				    calday.setDate(calday.getDate()+(idx-1));
				if (calday.getDay() == 0 || calday.getDay() == 6) continue;

				const year = calday.getFullYear()
				const month = datePad(calday.getMonth()+1);
				const day = datePad(calday.getDate());
				const date = (idx > 0 && idx <= this.lastday) ? year+'-'+month+'-'+day : null;
				const week = calday.getDay();
				const fullday = (year+'-'+month == ym) ? day : '';

				week_tmp.push({
					date: date,
					day: fullday,
					week: week,
					is_date: (date)
				});

				if (week == 5) {
					if (week_tmp.at(0).date || week_tmp.at(4).date) {
						data = data.concat(week_tmp);
					}
					week_tmp = [];
				}
			}
			return data;
		},
		getday(cell)
		{
			const day = (cell-this.calstart+1);
			return (day > 0 && this.lastday >= day) ? day : '';
		},
		getPlan(cal)
		{
			if (!cal.date) return;

            // 매 주마다 시작 시 배치 순서 초기화
            if (!this.lastweek || this.lastweek > cal.week) {
                this.cal_line = []; // 해당 주간 내에 존재하는 issue_offset(이슈 순서)
            }
            this.lastweek = cal.week;

			if (!this.plans[cal.date]) return;

            // 셀 내 스케쥴 배치 순서 및 간격 유지
            this.plans[cal.date].forEach((data) => {
                if (!this.cal_line.includes(data.issue_offset)) {
                    this.cal_line.push(data.issue_offset);
                }
            });

            // 셀에 일정 배치
            let   plan = [];
            for (let line in this.cal_line)  {
				plan[line] = [];
				this.plans[cal.date].forEach((data) => {
					if (data.issue_offset == this.cal_line[line]) {
						plan[line] = data;
					}
				});
			}
			return plan;
		},
		openIsuue(issue_idx)
		{
			if (!issue_idx) return false;
			api('/api/issue/get/'+issue_idx)
				.then((ret) => {
					if (ret.status == 'success') {
						window.open('/#/issue/view/'+issue_idx);
					}
				});
		},
		setYm(v) {
			let   calday = new Date(this.ym+'-01')
			      calday.setMonth(calday.getMonth()+v);
			const year = calday.getFullYear()
			const month = datePad(calday.getMonth()+1);
			this.search.ym = year+'-'+month;
		},
		setDesignMode(mode) {
			this.vertical = mode;
			window.localStorage.setItem('calendar-vertical', mode);
		},
		importance: function(e) {
			if (this.search.importance == parseInt(e.target.value)) {
				this.search.importance = 0;
			}
		},
		getSearch: function(k, v) {
			this.search[k] = v;
		}
	},
	mounted: function() {
		this.search = urlToParam(this.search);
		if (this.search.ym == this.to_month) this.search.ym = '';

		this.reload();
	},
	components: {
		staffs_search: Vue.defineAsyncComponent(() => loadModule('/resource/view/common/staffs_search.vue', options)),
	}
}
</script>