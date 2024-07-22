<template>
    <div id="issue" class="layoutHorizontal">
        <form class="leftArea flexible" id="searchForm">
            <input type="hidden" v-model="search.idx">
            <fieldset>
                <legend>업로더</legend>
                <staffs_search
                        name="creater"
                        source="staff/list"
                        :search.sync="search.creater"
                        @get-search="getSearch"
                ></staffs_search>
            </fieldset>

            <fieldset class="m_half">
                <legend>이슈제목</legend>
                <div>
                    <input type="text" @input="keyinput" :value="search.issue" name="issue" class="input_text input_full" placeholder="이슈제목">
                </div>
            </fieldset>

            <fieldset class="m_half">
                <legend>파일명</legend>
                <div>
                    <input type="text" @input="keyinput" :value="search.origin" name="origin" class="input_text input_full" placeholder="파일명">
                </div>
            </fieldset>

            <fieldset>
                <legend>등록일</legend>
                <div class="cal">
                    <datepicker name="registerd_s" @get-search="getSearch" :date="search.registerd_s"></datepicker>
                    <datepicker name="registerd_e" @get-search="getSearch" :date="search.registerd_e"></datepicker>
                </div>
            </fieldset>

            <fieldset>
                <legend>파일 종류</legend>
                <ul>
                    <li><label><input type="checkbox" value="xls" v-model="search.extension" @click.ctrl="checkboxRadio('extension', $event)"> xls</label></li>
                    <li><label><input type="checkbox" value="doc" v-model="search.extension" @click.ctrl="checkboxRadio('extension', $event)"> doc</label></li>
                    <li><label><input type="checkbox" value="ppt" v-model="search.extension" @click.ctrl="checkboxRadio('extension', $event)"> ppt</label></li>
                    <li><label><input type="checkbox" value="pdf" v-model="search.extension" @click.ctrl="checkboxRadio('extension', $event)"> pdf</label></li>
                    <li><label><input type="checkbox" value="txt" v-model="search.extension" @click.ctrl="checkboxRadio('extension', $event)"> txt</label></li>
                    <li><label><input type="checkbox" value="image" v-model="search.extension" @click.ctrl="checkboxRadio('extension', $event)"> image</label></li>
                </ul>
            </fieldset>
        </form>

        <div class="contentArea">
            <h2 class="title"><i class="xi-view-list xi-1x"></i> 파일 목록</h2>
            <table class="table tableHorizontal flexible">
                <colgroup>
                    <col style="width:110px;">
                    <col>
                    <col>
                    <col style="width:80px">
                    <col style="width:100px">
                </colgroup>
                <thead>
                <tr>
                    <th>등록일시</th>
                    <th>이슈</th>
                    <th>파일명</th>
                    <th>등록자</th>
                    <th>용량</th>
                </tr>
                </thead>
                <tbody>
                <tr v-if="!this.loading" v-for="data in list">
                    <td class="date">{{ data.registerd }}</td>
                    <td class="left">
                        <a href="#" @click.stop.prevent="viewIssue(data.issue_idx)">
                            {{ data.issue }}
                        </a>
                    </td>
                    <td class="left comment">
                        <a :href="'/api/file/download/'+data.idx" @click.prevent="read(data)">
                            <i v-if="data.referer=='issue'" class="xi-code"></i>
                            <i v-if="data.referer=='comment'" class="xi-forum-o"></i>
                            {{ data.origin }}
                        </a>
                    </td>
                    <td>{{ data.creater.name }}</td>
                    <td>{{ data.filesize_human }}</td>
                </tr>
				<tr v-if="list.length == 0 || loading">
					<td colspan="4"  :class="{nodata: true, loading: this.loading}"><i class="xi-info xi-1x"></i> 검색된 내역이 없습니다.</td>
				</tr>
                </tbody>
            </table>
            <div class="bottom">
                <div class="page_block" v-html="paginator"></div>
            </div>
        </div>
    </div>
</template>

<style scope>
.comment img {
    max-height: 100px;
    margin-right: 5px;
}
</style>
<script>
export default {
    setup: function() {
        return Vue.reactive({
            search: {
                page: 1,
                creater: [],
                issue: null,
                origin: null,
                register: null,
                extension: []
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
            handler: function () {
				// 검색 시 페이지 초기화
				if (this.page == this.search.page) this.search.page = 1;
				this.page = this.search.page;

				// 이동
                this.$router.push({
                    path: this.$route.path,
                    query: proxyToQuery(this.search)
                });
            }
        }
    },
    methods: {
        hashchanged: function () {
            if (this.$router.currentRoute.value.path != '/explorer') return false;

            clearTimeout(window.search_interval);
            window.search_interval = setTimeout(() => {
                this.search = urlToParam(this.search);
                this.reload();
            }, 200);
        },
        keyinput: function (e) {
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
        getSearch: function(k, v) {
            this.search[k] = v;
        },
        reload: function () {
			this.loading = true;
            api('/api/file/listAll', 'get', proxyToQuery(this.search)).then((ret) => {
                if (ret.status == 'success') {
                    this.list = ret.data;
                    this.paginator = ret.paginator;
                }
				this.loading = false;
            });
        },
        read: function(file) {
            const ext = file.mime.toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                imageViewer('/api/file/download/'+file.idx);
            } else {
                if (confirm('파일을 다운로드 하시겠습니까?')) {
                    location .href = '/api/file/download/'+file.idx+'?token='+window.localStorage.getItem('token');
                }
            }
            return true;
        },
        viewIssue: function(idx) {
            api('/api/issue/get/'+idx)
                    .then((ret) => {
                        if (ret.status == 'success') {
                            this.$router.push({
                                path: '/issue/view/'+idx
                            });
                        }
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