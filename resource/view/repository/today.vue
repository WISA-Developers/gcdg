<template>
	<div class="repository">
		<h2 class="title"><i class="xi-codepen xi-1x"></i> 저장소 커밋 내역</h2>
		<div v-if="!this.loading" v-for="log in list">
			<div class="ref"><span>{{ log.rev_v }}</span></div>
			<div class="message">
				<router-link :to="'/repository/'+log.rev">
					{{ log.message }}
				</router-link>				
			</div>
		</div>

		<div v-if="list.length == 0 || loading" :class="{nodata: true, loading: this.loading}">
			<i class="xi-info xi-1x"></i> 커밋 내역이 없습니다.
		</div>
	</div>
</template>

<script>
export default {
    setup: function() {
        return Vue.reactive({
            list: [],
			loading: true
        })
    },
    methods: {
        reload: function() {
			this.loading = true;
            api('/api/repository/today')
                .then((ret) => {
                    if (ret.status == 'success') {
                        this.list = ret.data;

                        const nodata = document.querySelector('.nodata');
                        if (nodata) nodata.classList.remove('loading');
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