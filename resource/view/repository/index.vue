<template>
	<div id="issue" class="layoutHorizontal">
		<form class="leftArea flexible" id="searchForm">

			<fieldset>
				<legend>메시지</legend>
				<div>
					<input type="text" @input="keyinput" :value="search.search_str" name="search_str" class="input_text input_full" placeholder="메시지">
				</div>
			</fieldset>

            <fieldset>
                <legend>커밋일자</legend>
                <div class="cal">
                    <datepicker name="date_s" @get-search="getSearch" :date="search.date_s" :placeholder="def_date_s"></datepicker>
                    <datepicker name="date_e" @get-search="getSearch" :date="search.date_e" :placeholder="def_date_e"></datepicker>
                </div>
            </fieldset>
		</form>

		<div class="contentArea">
			<h2 class="title"><i class="xi-view-list xi-1x"></i> 저장소 목록</h2>

			<div class="info_message">
				<strong>{{ repository.type }}</strong> | {{ repository.url }}
			</div>

            <table class="table tableHorizontal flexible">
                <colgroup>
                    <col style="width:80px;">
                    <col style="width:110px">
                    <col style="width:110px">
                    <col>
                </colgroup>
                <thead>
                <tr>
                    <th v-if="repository.type == 'GIT'">태그</th>
                    <th>버전</th>
                    <th>처리일시</th>
                    <th>처리자</th>
                    <th>내용</th>
                </tr>
                </thead>
                <tbody>
                <tr v-if="!this.loading" v-for="data in list">
                    <td v-if="repository.type == 'GIT'">
                        {{ data.tag }}
                    </td>
                    <td style="overflow: hidden; text-overflow: ellipsis;">
                        r{{ data.rev }}
                    </td>
                    <td class="date">{{ data.date }}</td>
                    <td>{{ data.author}}</td>
                    <td class="left">
                        <router-link :to="'/repository/'+data.rev">
                         {{ data.message }}
                        </router-link>
                    </td>
                </tr>
                <tr v-if="list.length == 0 || loading">
                    <td colspan="4" :class="{nodata: true, loading: this.loading}"><i class="xi-info xi-1x"></i> 검색된 내역이 없습니다.</td>
                </tr>
                </tbody>
            </table>
		</div>
	</div>
</template>

<script>
export default {
	setup: function() {
		return Vue.reactive({
			repository: {
				type: null,
				url: null
			},
			search: {
                search_str: null,
                date_s: null,
                date_e: null
			},
            list: []
		});
	},
    data: function() {
        let date_s = new Date();
        date_s.setMonth(date_s.getMonth()-3);

        return {
            loading: true,
            def_date_s: date_s.toISOString().substring(0, 10),
            def_date_e: (new Date()).toISOString().substring(0, 10)
        }
    },
    watch: {
        search: {
            deep: true,
            handler: function () {
                // 이동
                this.$router.push({
                    path: this.$route.path,
                    query: proxyToQuery(this.search)
                });
            }
        }
    },
    methods: {
        hashchanged: function() {
            if (this.$router.currentRoute.value.path != '/repository') return false;

            clearTimeout(window.search_interval);

            window.search_interval = setTimeout(() => {
                this.loading = true;
                this.search = urlToParam(this.search);
                this.reload();
            }, 200);
        },
        keyinput: function(e) {
            const input = e.target;
            const name = input.getAttribute('name');
            this.search[name] = input.value;
        },
        reload: function() {
            this.loading = true;
            api('/api/repository/logs', 'get', proxyToQuery(this.search)).then((ret) => {
                if (ret.status == 'success') {
                    this.list = ret.data;
                }
                this.loading = false;
            });
        },
        getSearch: function(k, v) {
            this.search[k] = v;
        }
    },
	beforeMount: function() {
		api('/api/repository/info')
			.then((ret) => {
				if (ret.status == 'success') {
					this.repository = ret.data;
                    this.search = urlToParam(this.search);

                    this.reload();
				} else {
					this.$router.go(-1);
				}
			});
	},
	components: {
		datepicker
	}
}
</script>