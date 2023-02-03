<template>
    <ul class="mypage_summary">
        <li>소속 프로젝트 : <strong>{{ data.my_projects }}</strong></li>
        <li>작성 이슈 : <strong>{{ data.my_issues }}</strong></li>
        <li>작성 코멘트 : <strong>{{ data.my_comments }}</strong></li>
    </ul>
</template>

<style scope>
.mypage_summary {
	margin: 20px 0 0 0;
	padding: 0;
	list-style-type: none;
	border-top: solid 1px var(--table-border-strong-color);
}

.mypage_summary li {
	padding: 10px 5px;
	border-bottom: solid 1px var(--table-border-strong-color);
}
</style>

<script>
export default {
	setup() {
		return Vue.reactive({
			data: {}
		})
	},
	beforeMount: function() {
		api('/api/site/summary')
			.then((res) => {
				if (res.status == 'success') {
					this.data = res.data;
				}
			});
	}
}
</script>