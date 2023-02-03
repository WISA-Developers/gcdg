<template>
    <div id="adminList">
        <h2 class="title"><i class="xi-man xi-1x"></i> 최고관리자 설정</h2>

        <table class="table tableHorizontal flexible">
            <colgroup>
                    <col style="width:140px;">
                    <col>
            </colgroup>
            <thead>
            <tr>
                <th>등록일시</th>
                <th>이름</th>
                <th>부서</th>
            </tr>
            </thead>
            <tbody>
                <tr v-if="!this.loading" v-for="staff in list">
                    <td class="date">{{ staff.registerd }}</td>
                    <td>{{ staff.name }}</td>
                    <td class="left content" style="padding-right: 30px">
                        {{ staff.group_name }}
                        <div class="buttons">
                            <a href="#" @click.prevent="remove(staff.idx)"><i class="xi-trash"></i></a>
                        </div>						
                    </td>
                </tr>
				<tr v-if="list.length == 0 || loading">
					<td colspan="3"  :class="{nodata: true, loading: this.loading}"><i class="xi-info xi-1x"></i> 생성된 키가 없습니다.</td>
				</tr>
            </tbody>
        </table>
        <div class="bottom">
            <div class="page_block" v-html="paginator"></div>
            <div class="button_block">
                <span class="button"><input type="button" value="생성" @click="open"></span>
            </div>
        </div>

        <form class="adminFrm modal" @submit.stop="generate">
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
                        <th>관리자</th>
                        <td>
                            <admin_staff
                                name="staff_idx"
                                source="staff/list"
                            ></admin_staff>
                        </td>
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
</template>

<script>
export default {
	data: function() {
		return {
			list: [],
			loading: true
		}
	},
    methods: {
        reload: function() {
			this.loading = true;
            api('/api/site/admins')
                .then((ret) => {
                    if (ret.status == 'success') {
                        this.list = ret.list;
                    }
					this.loading = false;
                });
        },
        open: function()
        {
            $('.adminFrm').css('display', 'flex').fadeIn();
        },
        close: function() {
            $('.adminFrm').fadeOut();
        },
        generate: function()
        {
            const staff_idx = document.querySelectorAll('input[name="staff_idx[]"]');
            let   data = [];
            staff_idx.forEach((s) => {
                data.push(s.value);
            });
            api('/api/site/setAdmin', 'get', {'staff_idx': data})
                .then((res) => {
                    $('.input_auto  label').remove();
                    document.querySelector('input[type=text]').value = '';
                    if (res.status == 'success') {
                        this.reload();
                        this.close();
                    }
                });
        },
        remove: function(idx)
        {
            if (!confirm('최고관리자를 삭제하시겠습니까?')) {
                return false;
            }
            api('/api/site/adminRemove/'+idx)
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
    components: {
        admin_staff: Vue.defineAsyncComponent(() => loadModule('/resource/view/common/staffs_search.vue', options)),
    }
}
</script>