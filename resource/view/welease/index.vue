<template>
    <div id="welease" class="layoutHorizontal">
        <div class="leftArea target">
			<fieldset>
				<legend>패치 대상 서버</legend>
				<select class="servers" multiple>
					<option :value="server.url" v-for="server in servers">[{{ server.name }}] {{ server.ip }}</option>
				</select>
			</fieldset>
        </div>

        <div class="contentArea">
            <div class="svn_auth">
                <form @submit.stop.prevent="getSVNLog">
                    <ul class="repo_types">
                        <li><label><input type="radio" name="branch" value="dev" @change="clearSVNLog"> dev</label></li>
                        <li><label><input type="radio" name="branch" value="stable" @change="clearSVNLog" checked> stable</label></li>
                    </ul>
                    <span class="inputs">
                        <input type="text" name="svn_id" class="input_text" size="9" placeholder="svn 아이디">
                        <input type="password" name="svn_pw" class="input_text" size="9" placeholder="svn 비밀번호" autocomplete="new-password">
                    </span>
                    <span class="button ok"><input type="submit" value="로그 조회"></span>
                </form>
            </div>

            <div class="svn_log">
                <select class="rev" size="10">
                    <option v-for="data in revs" :value="data.rev">{{ data.date }} | r{{ data.rev }} : {{ data.message }}</option>
                </select>
            </div>

            <div class="bottom">
                <span class="button"><input type="submit" value="패치요청" @click="release"></span>
            </div>

            <div class="log">
                <ul>
                    <li v-for="data in log" :class='data.kind'><span v-if="data.item">{{ data.item }}</span> {{ data.text }}</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    setup: function() {
        return Vue.reactive({
            servers: {},
            revs: [],
            log: [],
            svn_id: '',
			svn_pw: '',
			branch: '',
        })
    },
    methods: {
        getSVNLog: function(event) {
            const f = event.target;
            api('/api/welease/getSVNLog', 'post', f)
                .then((ret) => {
                    if (ret.status == 'success') {
                        if (ret.log.length == 0) {
                            window.alert('SVN접속 정보를 확인해주세요.')
                        }
                        this.revs = ret.log;
                        this.svn_id = f.svn_id.value;
						this.svn_pw = f.svn_pw.value;
						this.branch = f.branch.value;
                    }
                });
        },
        clearLog: function() {
            this.svn = null;
        },
        release: function() {
            const select_server = document.querySelectorAll('select.servers option:checked');
            const select_rev = document.querySelectorAll('select.rev option:checked');
            if (!select_server.length) {
                window.alert('적용할 서버를 선택해주세요.');
                return false;
            }
            if (!select_rev.length) {
                window.alert('적용할 리비전을 선택해주세요.');
                return false;
            }
			/*
            const check = window.prompt(
                `- 소스가 overwrite되며 별도 백업이 되지 않습니다.\n`+
                `- 여러 리비전 배포 시 리비전이 빠른 순서대로 실행해주세요.\n`+
                `- 배포 후 반드시 테스트를 진행하세요.\n\n`+
                `선택한 리비전을 배포하시겠습니까?\n`+
                `'배포합니다' 또는 'qovhgkqslek'라고 입력해주세요.`
            );
            if (check != '배포합니다' && check != 'qovhgkqslek') {
                window.alert('취소되었습니다.');
                return false;
            }
			*/

            printLoading();

            const rev = select_rev[0].value; // 배포할 버전
            this.release_servers = select_server.length;
            this.released_servers = 0;
            select_server.forEach((o) => {
                const url = o.value;
                
                api('/api/welease/release?svn_id='+this.svn_id+'&svn_pw='+this.svn_pw+'&branch='+this.branch+'&rev='+rev+'&url='+url, 'get')
                    .then((ret) => {
						this.log = this.log.concat(ret.data);

                        this.released_servers++;
                        if (this.release_servers == this.released_servers) {
                            this.release_servers = this.released_servers = null;
                            removeLoading();
                        }

                        $('#welease > .contentArea > .log > ul').animate({
                            'scrollTop': $('#welease > .contentArea > .log > ul').prop('scrollHeight')
                        });
                    });
            });
        }
    },
    beforeMount: function() {
		api('/api/welease/otp?mode=request&code='+window.localStorage.getItem('welease_auth_code'))
			.then((auth) => {
				if (auth.status != 'success') return false;

				if (auth.auth == 'true') {
					api('/api/welease/servers')
						.then((ret) => {
						   this.servers = ret.data;
						});
				} else {
					let code = window.prompt('수신받은 코드를 입력해주세요.');
					api('/api/welease/otp?code='+code)
						.then((auth) => {
							if (auth.auth == 'true') {
								window.localStorage.setItem('welease_auth_code', code);
								api('/api/welease/servers')
									.then((ret) => {
									   this.servers = ret.data;
									});
							} else {
								window.alert('코드가 정확하지 않습니다.');
								this.$router.push({
									path: '/dashboard'
								});
							}
						});
				}
			});
    },
    updated: function() {
        if (window.release_servers == null) {
            window.release_servers = 0;
            $('#welease .contentArea > .log > ul').animate({'scrollTop': $('#welease .contentArea > .log > ul').prop('scrollHeight')});
        }
    }
}
</script>