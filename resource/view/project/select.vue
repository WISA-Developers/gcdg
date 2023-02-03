<template>
	<div class="project_select" v-cloak>
		<h2 class="title"><i class="xi-search xi-1x"></i> 작업할 프로젝트를 선택해주세요.</h2>

		<div class="no_data">
			<i class="xi-ban"></i> <strong>초대받은 프로젝트가 없습니다.</strong>
			<ul>
				<li>프로젝트 관리자에게 프로젝트 초대를 요청해주세요.</li>
				<li>새로운 프로젝트를 생성하려면 최고 관리자에게 요청해주세요.</li>
			</ul>
		</div>

		<ul class="project">
			<li v-for="project in list" @click="setProjectIdx(project)">
				<h2>{{ project.project_name }}</h2>
				<p v-html="project.content"></p>
			</li>
		</ul>

		<div v-if="list.length == 0">
			초대된 프로젝트가 없습니다.<br>
			관리자 또는 프로젝트 매니저를 통해 프로젝트 참여해주세요.
		</div>
	</div>
</template>

<script>
export default {
    data: function() {
        return {
            list: [],
        }
    },
    methods: {
        setProjectIdx: function(project)
        {
			api('/api/project/select/'+project.idx)
                .then((res) => {
                    if (res.status == 'success') {
                        this.$emit('project-select', res.project_idx);
                    }
                });
        }
    },
    beforeMount: function() {
		api('/api/project/my').then((res) => {
			this.list = res.data;
		});
    }
}
</script>