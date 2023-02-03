<template>
	<div class="layoutCenter dashboard">
        <today></today>
		<repository v-if="me.current_repository"></repository>
	</div>
</template>

<style scope>
.dashboard .repository > ul {
    margin: 0;
    padding: 0;
    list-style-type: none;
}

.dashboard .repository > ul > li {
    padding: 5px 0;
}

.dashboard .repository > ul > li .version {
    display: inline-block;
    padding: 3px 5px;
    margin: 0 5px 5px 0;
    border: solid 1px var(--table-border);
    border-radius: 5px;
    background-color: var(--highlight-color);
    white-space: nowrap;
    font-size: 0.9em;
    color: var(--main-font-color);
}
</style>

<script>
export default {
    props: {
        me: Array
    },
    components: {
        'today': Vue.defineAsyncComponent(() => loadModule('/resource/view/schedule/today.vue', options)),
		'repository': Vue.defineAsyncComponent(() => loadModule('/resource/view/repository/today.vue', options))
    }
}
</script>