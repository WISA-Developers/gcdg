<template>
    <div class="schedules">
        <h2 class="title"><i class="xi-calendar-check xi-1x"></i> '{{ me.current_project_name }}' 오늘의 업무</h2>
        <div v-if="!this.loading" v-for="staff in list">
            <h3><span class="portrait"><img :src="staff.portrait"></span> {{ staff.name }}</h3>
            <ul>
                <li
                    v-for="item in staff.items"
                    :style="{'border-color': 'var(--cal-sch-'+issue_offset[item.idx]+')'}"
                >
                    <router-link :to="'/issue/view/'+item.idx">{{ item.title }}&nbsp;</router-link>
                </li>
            </ul>
        </div>

        <div v-if="list.length == 0" :class="{nodata: true, loading: this.loading}">
            <i class="xi-info xi-1x"></i> 오늘의 업무가 없습니다.
        </div>
    </div>
</template>

<script>
export default {
    props: {
        me: Array
    },
    setup: function() {
        return Vue.reactive({
            list: [],
            issue_offset: [],
			loading: true
        })
    },
    methods: {
        reload: function() {
			this.loading = true;
            api('/api/schedule/today')
                .then((ret) => {
                    if (ret.status == 'success') {
                        this.list = ret.data;
                        this.issue_offset = ret.issue_offset;
                    }
					this.loading = false;
                });
        }
    },
    beforeMount: function() {
        this.reload();
    }
}
</script>