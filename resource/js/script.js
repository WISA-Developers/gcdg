const options = {
    moduleCache: {
    vue: Vue,
    },
    getFile(url) {
        return fetch(url).then(response => response.ok ? response.text() : Promise.reject(response));
    },
    addStyle(styleStr) {
        const style = document.createElement('style');
        style.textContent = styleStr;
        const ref = document.head.getElementsByTagName('style')[0] || null;
        document.head.insertBefore(style, ref);
    },
    log(type, ...args) {
        console.log(type, ...args);
    }
}

const { loadModule } = window["vue3-sfc-loader"];

function api(url, method, param) {
	const token = window.sessionStorage.getItem('token');
	let   body  = null;

	if (!method) method = 'GET';
	if (param) {
		if (method == 'get') {
			for (let k in param) {
				if (param[k] == null) delete param[k];
			}
			const p = (new URLSearchParams(param)).toString().replace('%2B', '%20');
			if (p) {
				url += (url.indexOf('?') > -1) ? '&' : '?';
				url += p;
			}
		}
		if (method == 'post') {
			if (typeof param == 'object' && param.tagName && param.tagName == 'FORM') {
				body = new FormData(param);
			} else {
				body = new FormData();
				for (k in param) {
					body.append(k, param[k]);
				};
			}
		}
	}
	if (token) {
		url += (url.indexOf('?') > -1) ? '&' : '?';
		url += 'token='+token;
	}

	return new Promise(function(resolve, reject) {
		fetch(url, {
			method: method,
			body: body
		})
		.then((res) => res.json())
		.then((res) => {
			if (res.status == 'error') {
				window.alert(res.message);

				if (res.code == '9999') { // 인증 오류
					location.href = '/';
					return false;
				}
			}
			resolve(res);
		});
	})
};

function proxyToQuery(prxy) 
{
	let query = {};
	for (let k in prxy) {
		if (prxy[k]) {
			if (typeof prxy[k] == 'object') {
				if (prxy[k].length > 0) {
					query[k] = prxy[k].join(',');
				}
			} else {
				query[k] = prxy[k];
			}
		}
	}
	return query;
}

function urlToParam(search) {
	const uri = new URL(location.href);
	const params = (new URL(uri.origin+'?'+uri.hash.split('?').at(1))).searchParams;

	for (let k in search) {
		let v = params.get(k);
		let type = typeof search[k];

		if (type == 'object') {
			if (v) v = params.get(k).split(',');
			else v = [];
		} else {
			if (v) v = decodeURIComponent(params.get(k).replace('+', '%20'));
			else v = (type == 'number') ? 0 : '';
		}
		search[k] = v;
	}
	return search;
}

function datePad(number) {
	if (number < 10) return '0'+number;
	return number.toString();
}

const datepicker = {
	data() {
		return {
			dp: null,
			value: this.placeholder || null
		}
	},
	props: {
		name: String,
		date: String,
		placeholder: String
	},
	watch: {
		'date': function() {
			this.value = this.date;
		}
	},
    template: '<div @click="reset"><input class="input_text" :name="name" :value="value" :placeholder="placeholder" @change="searching"></div>',
	methods: {
		reset: function(e) {
			if (e.target.tagName == 'DIV') {
				this.dp.datepicker('setDate', '');
				this.$emit('get-search', this.name, '')
			}
		}
	},
	beforeMount: function() {
		if (this.date) this.value = this.date;
	},
    mounted: function() {
		const self = this;
        this.dp = $(self.$el.querySelector('input')).datepicker({
            monthNamesShort: ['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'],
            dayNamesMin: ['일','월','화','수','목','금','토'],
            weekHeader: 'Wk',
            dateFormat: 'yy-mm-dd',
            autoSize: false,
            changeYear: true,
            changeMonth: true,
            showButtonPanel: true,
            currentText: '오늘',
            closeText: '닫기',
			onSelect: function(d) {
				self.$emit('get-search', this.name, d) 
			}
        });
    }
};

function sethash(json)
{
    const url = location.href;
    const parsed_uri = new URL(url);
    let   hash = parsed_uri.hash;

    for (let key in json) {
        const regex = new RegExp('(\\?|&)'+key+'=([^&]+)');
        if (regex.test(hash)) {
            hash = hash.replace(regex, '$1'+key+'='+json[key]);
        } else {
            hash += (hash.indexOf('?') > -1) ? '&' : '?';
            hash += key+'='+json[key];
        }
    }
    location.href = hash;
}

function ajaxUpload(data, blob, callback)
{
	printLoading();

    const formData = new FormData();
    formData.append('image', blob);
	for (let key in data) {
		formData.append(key, data[key]);
	};

    /*
    var reader = new FileReader();
    reader.addEventListener(
        'load',
        function () {
            console.log(reader.result);
        },
        false
    );
    reader.readAsDataURL(blob);
    */

    $.ajax({
        type: 'POST',
        enctype: 'multipart/form-data',
        url: '/api/file/upload?token='+window.sessionStorage.getItem('token'),
        data: formData,
        dataType: 'json',
        processData: false,
        contentType: false,
        success: function(res) {
			removeLoading();
            if (res.status == 'success') {
                if (callback) callback(res.filename);
            } else {
                window.alert(res.message);
            }
        }
    });
}

function printLoading() {
	if (!document.getElementById('dimmed')) {
		$('body').append('<div id="dimmed"></div>');
	}
}

function removeLoading() {
    $('#dimmed').remove();
}

function imageViewer(filename)
{
	const token = window.sessionStorage.getItem('token');
	const viewer = $('<div id="image_viewer"><img src="'+filename+'?token='+token+'"></div>');
	$('body').append(viewer);
	viewer.on('click', function() {
		$(this).remove();
	});

}

function floating(message)
{
	const _f = $('<div class="floating"><i class="xi-note xi-2x" style="color: var(--point-color);"></i><span></span></div>');
	$('body').append(_f);
	
	_f.find('span').html(message);
	_f.fadeIn(500).css('display', 'flex').delay(1000).fadeOut(500, function() {
		_f.remove();
	});
}