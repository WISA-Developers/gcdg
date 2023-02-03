<template>
	<div id="dashboard">
		<project v-if="!me.current_project_idx" @project-select="projectSelect"></project>
		<dashboard v-if="me.current_project_idx" :me="me"></dashboard>
	</div>
</template>

<script>
export default {
	props: {
		me: Array
	},
	methods: {
        projectSelect: function(project_idx) {
            this.$emit('project-select', project_idx)
		}
	},
	components: {
		dashboard: Vue.defineAsyncComponent(() => loadModule('/resource/view/dashboard/dashboard.vue', options)),
		project: Vue.defineAsyncComponent(() => loadModule('/resource/view/project/select.vue', options))
	}
}
</script>