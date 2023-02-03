<template>
    <table id="component_chain" class="table tableHorizontal flexible">
        <colgroup>
            <col style="width: 130px">
            <col style="width: 90px">
            <col>
        </colgroup>
        <thead>
        <tr>
            <th>작성일시</th>
            <th>처리상태</th>
            <th>제목</th>
        </tr>
		</thead>
        <tbody>
        <tr v-for="issue in chain">
            <td class="date">{{ issue.registerd }}</td>
            <td>{{ issue.status }}</td>
            <td class="left content" style="padding-right: 30px">
                <router-link :to="'/issue/view/'+issue.idx">
					<span :class="{'xi-star': true, ['importance_'+data.importance]: true, 'attr_icon': true}">{{ data.importance }}</span>
					<i v-if="issue.device.includes('PC')" class="xi-desktop attr_icon"></i>
					<i v-if="issue.device.includes('android')" class="xi-android attr_icon"></i>
					<i v-if="issue.device.includes('IOS')" class="xi-apple attr_icon"></i>
					<i v-if="issue.device.includes('APP')" class="xi-apps attr_icon"></i>
					{{ issue.title }}
				</router-link>
				<div class="buttons">
					<a href="#" @click.prevent="remove(issue.chain_idx)"><i class="xi-trash"></i></a>
				</div>
            </td>
        </tr>					
        </tbody>
    </table>
    <div class="bottom">
        <div class="page_block" v-html="paginator"></div>
        <div class="button_block">
            <span class="button ok"><input type="button" value="연결" @click="searchLink"></span>
        </div>
    </div>

	<div class="issue_chain_link modal">
		<div class="dimmed"></div>
		<div class="form">
			<h2>
				<i class="xi-link"></i>&nbsp;&nbsp;이슈 검색
			</h2>
			<div style="margin: 5px 0">
				<input type="text" class="input_text input_full" placeholder="검색할 이슈제목을 입력해주세요" @input="searchLink">
			</div>
			<div style="height:40vh; overflow: overay">
				<table id="component_chain" class="table tableHorizontal">
					<colgroup>
						<col style="width: 50px">
					</colgroup>
					<thead>
						<tr>
							<th>선택</th>
							<th>이슈 제목</th>
						</tr>	
					</thead>
					<tbody>
						<tr v-for="issue in search">
							<td><input type="checkbox" name="idx[]" :id="'issue_'+issue.idx" :value="issue.idx"></td>
							<td class="left content">
								<label :for="'issue_'+issue.idx">{{ issue.title }}</label>
								<div class="buttons">
									<a :href="'/issue/view/'+issue.idx" target="_blank"><i class="xi-link-insert"></i></a>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="bottom">
				<div class="button_block">
					<span class="button ok"><input type="button" value="연결" @click="link"></span>
					<span class="button"><input type="button" value="닫기" @click="searchClose"></span>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	data: function() {
		return {
			page: 0,
			paginator: null,
			chain: [],
			search: [],
			modify: {},
			viewer: [],
			exists: []
		}
	},
	props: {
		data: Array
	},
	methods: {
		hashchanged: function() {
			this.reload()
		},
		reload: function() {
			const uri = new URL(location.href);
			const suri = new URL(uri.origin+'?'+uri.hash.split('?').at(1));
			const page = suri.searchParams.get('i') || 1;

			api('/api/issue/chain/'+this.$route.params.idx+'/'+page+'?limit=5')
				.then((ret) => {
					if (ret.status == 'success') {
						if (ret.count > 0 && ret.data.length == 0) { // 삭제 후 현재 페이지 없을 경우
							ret.page--;
							sethash({i: ret.page, l: 'i'});
							return false;
						}

						this.page = ret.page;
						this.paginator = ret.paginator;
						this.chain = ret.data;

						this.$emit('get-data', 'comoponent_chain_count', ret.count);

						// 중복 제거용
						this.exists = [];
						ret.data.forEach((c) => {
							this.exists.push(c.idx);
						});
						this.exists.push(this.data.idx);
					}
				});
		},
		searchLink: function(e) {
			if (e && e.target && e.target.tagName == 'INPUT' && e.target.value) {
				clearTimeout(window.search_interval);
				window.search_interval = setTimeout(() => {
					api('/api/issue/index', 'get', {title: e.target.value, except: this.exists.join(','), limit: 20})
						.then((ret) => {
							if (ret.status == 'success') {
								this.search = ret.data;
							}
						});
				}, 200);
			} 
			$('.issue_chain_link').css('display', 'flex').fadeIn();
		},
		searchClose: function() {
			$('.issue_chain_link').fadeOut();
			$('.issue_chain_link input[type=text]').val('');
			this.search = [];
		},
		link: function(e) {
			printLoading();

			const checked = $(e.target).parents('.issue_chain_link').find(':checked[name="idx[]"]');
			if (checked.length == 0) {
				window.alert('연결할 이슈를 선택해주세요.');
				return false;
			}

			let chain_idx = '';
			checked.each(function() {
				chain_idx += ','+this.value;
			});
			api('/api/issue/addChain/'+this.data.idx, 'get', {'chain_idx': chain_idx})
				.then((ret) => {
					removeLoading();
					if (ret.status == 'success') {
						sethash({i: 1});
						this.reload();
						this.searchClose();
					}
				});
		},
		remove: function(idx) {
			if (!window.confirm('선택하신 연결을 삭제하시겠습니까?')) {
				return false;
			}

			api('/api/issue/removeChain/'+idx)
				.then((ret) => {
					if (ret.status == 'success') {
						sethash({l: 'i'});
						this.reload();
					}
				});

			//로딩 후 스크롤 이동
			if (this.data.scrollNext && this.data.scrollNext == 'i') {
				let o = $('#component_chain');
				$('.contentArea').animate({
					'scrollTop': o.offset().top+$('.contentArea').scrollTop()-$('.contentArea').offset().top-10
				});
			}
		}
	},
	mounted: function() {
		this.hashchanged();
	}
}
</script>