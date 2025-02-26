<template>
    <div class="dialog">
        <div>
            <h2 class="title">
                이슈 조회 내역
                <a class="close" @click.stop.prevent="$emit('close')">X</a>
            </h2>
            <ul class="history-list">
                <li v-for="item in data">
                    {{ item.registerd }} ({{ item.gap }})
                </li>
            </ul>
            <div v-if="typeof data != null && data.length == 0" class="nodata">
                조회 내역이 없습니다.
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: [
        'hist'
    ],
    data: function() {
        return {
            data: null
        }
    },
    methods: {
        get: function() {
            api('api/issue/history/' + this.hist.issue_idx + '/' + this.hist.staff_idx)
                .then(ret => {
                    if (ret.status == 'success') {
                        this.data = ret.data;
                    }
                });
        }
    },
    mounted: function() {
        this.get();
    }
}
</script>