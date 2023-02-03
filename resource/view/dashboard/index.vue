<template>
	<div id="dashboard">
		<project v-if="!staff.current_project_idx" @set-Current-Project-Idx="setProjectIdx"></project>
		<dashboard v-if="staff.current_project_idx"></dashboard>
	</div>
</template>

<script>
export default {
	data: function() {
		return {
			staff: this.me
		}
	},
	props: {
		me: Array
	},
	methods: {
		setProjectIdx: function(idx) {
			this.staff.current_project_idx = idx;
		}
	},
	components: {
		dashboard: Vue.defineAsyncComponent(() => loadModule('/resource/view/dashboard/dashboard.vue', options)),
		project: Vue.defineAsyncComponent(() => loadModule('/resource/view/project/select.vue', options))
	}
}
</script>