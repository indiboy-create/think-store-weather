(function () {
  'use strict';

  var cfg = window.WEATHER_CONFIG || {};
  var state = {
    tree: [],
    stationid: cfg.defaultStation || '',
    data: null,
    loading: false,
    form: null,
    layer: null
  };

  var WEEK = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];

  function el(id) {
    return document.getElementById(id);
  }

  function isMissing(value) {
    return value === undefined
      || value === null
      || value === ''
      || value === '-'
      || value === 9999
      || value === '9999'
      || value === '9999.0';
  }

  function show(value, suffix) {
    if (isMissing(value)) {
      return '—';
    }

    return suffix ? value + suffix : String(value);
  }

  function fixed(value, digits, suffix) {
    if (isMissing(value) || isNaN(Number(value))) {
      return '—';
    }

    return Number(value).toFixed(digits) + (suffix || '');
  }

  function escapeHtml(value) {
    return String(value === undefined || value === null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function notify(message) {
    if (state.layer && typeof state.layer.msg === 'function') {
      state.layer.msg(message, { icon: 2, time: 2600 });
      return;
    }

    window.alert(message);
  }

  function getJSON(url) {
    return fetch(url, { headers: { Accept: 'application/json' } }).then(function (response) {
      return response.json().then(function (body) {
        if (!response.ok) {
          throw new Error(body && body.msg ? body.msg : '请求失败，HTTP ' + response.status);
        }

        return body;
      });
    });
  }

  function iconFor(text) {
    var value = String(text || '');

    if (value.indexOf('雷') > -1) return '⛈️';
    if (value.indexOf('雪') > -1) return '❄️';
    if (value.indexOf('雨') > -1) return '🌧️';
    if (value.indexOf('雾') > -1 || value.indexOf('霾') > -1) return '🌫️';
    if (value.indexOf('阴') > -1) return '☁️';
    if (value.indexOf('多云') > -1) return '⛅';
    if (value.indexOf('晴') > -1) return '☀️';

    return '🌤️';
  }

  function renderSelects() {
    if (state.form) {
      state.form.render('select');
    }
  }

  function findProvince(value) {
    for (var i = 0; i < state.tree.length; i++) {
      if (String(state.tree[i].id) === String(value)) {
        return state.tree[i];
      }
    }

    return null;
  }

  function fillCities(province, preferred) {
    var cityEl = document.querySelector('select[name="city"]');
    var cities = (province && province.children) || [];

    cityEl.innerHTML = cities.map(function (city) {
      return '<option value="' + escapeHtml(city.code) + '">' + escapeHtml(city.title) + '</option>';
    }).join('');

    var target = null;

    for (var i = 0; i < cities.length; i++) {
      if (cities[i].code === preferred) {
        target = cities[i];
        break;
      }
    }

    if (!target && cities.length > 0) {
      target = cities[0];
    }

    if (target) {
      cityEl.value = target.code;
      state.stationid = target.code;
    }

    renderSelects();

    return target;
  }

  function loadProvinces() {
    return getJSON(cfg.apiBase + '/provinces').then(function (tree) {
      state.tree = Array.isArray(tree) ? tree : [];

      var provinceEl = document.querySelector('select[name="province"]');

      provinceEl.innerHTML = state.tree.map(function (province) {
        return '<option value="' + escapeHtml(province.id) + '">' + escapeHtml(province.title) + '</option>';
      }).join('');

      var province = null;

      for (var i = 0; i < state.tree.length; i++) {
        if (state.tree[i].title === cfg.defaultProvince) {
          province = state.tree[i];
          break;
        }
      }

      province = province || state.tree[0];

      if (province) {
        provinceEl.value = String(province.id);
        fillCities(province, cfg.defaultStation);
      }

      renderSelects();
    });
  }

  function setLoading(loading) {
    state.loading = loading;

    var button = el('wx-refresh');

    if (button) {
      button.disabled = loading;
      button.style.opacity = loading ? '0.65' : '1';
    }
  }

  function queryWeather(code, force) {
    if (!code || state.loading) {
      return Promise.resolve();
    }

    if (!force && code === state.stationid && state.data) {
      return Promise.resolve();
    }

    setLoading(true);

    return getJSON(cfg.apiBase + '/weather?stationid=' + encodeURIComponent(code))
      .then(function (body) {
        state.stationid = code;
        state.data = body;
        render(body);
      })
      .catch(function (error) {
        notify(error.message);
      })
      .then(function () {
        setLoading(false);
      });
  }

  function render(body) {
    var data = (body && body.data) || {};
    var real = data.real || {};
    var weather = real.weather || {};
    var wind = real.wind || {};
    var air = data.air || {};
    var station = real.station || {};
    var cityEl = document.querySelector('select[name="city"]');
    var cityName = station.city
      || (cityEl && cityEl.selectedIndex >= 0 ? cityEl.options[cityEl.selectedIndex].text : '')
      || cfg.defaultCity;

    el('wx-city').textContent = cityName;

    var playerCity = el('wx-player-city');

    if (playerCity) {
      playerCity.textContent = cityName;
    }

    el('wx-publish').textContent = real.publish_time ? '更新于 ' + real.publish_time : '更新时间未知';
    el('wx-temp').textContent = fixed(weather.temperature, 1);
    el('wx-info').textContent = isMissing(weather.info) ? '天气现象暂无' : weather.info;
    el('wx-feel').textContent = '体感 ' + fixed(weather.feelst, 1) + '°C';
    el('wx-icon').textContent = iconFor(weather.info);

    el('wx-humidity').textContent = fixed(weather.humidity, 0, '%');
    el('wx-wind-power').textContent = isMissing(wind.direct) ? '—' : wind.direct + ' ' + show(wind.power);
    el('wx-pressure').textContent = isMissing(weather.airpressure) ? '—' : fixed(Number(weather.airpressure) / 10, 1, ' hPa');
    el('wx-rain').textContent = fixed(weather.rain, 1, ' mm');

    var windTag = el('wx-wind');
    windTag.textContent = isMissing(wind.speed)
      ? show(wind.power)
      : show(wind.power) + ' · ' + fixed(wind.speed, 1, ' m/s');

    var aqiTag = el('wx-aqi');

    if (isMissing(air.aqi)) {
      aqiTag.textContent = 'AQI —';
      aqiTag.className = 'wx-tag is-ghost';
    } else {
      aqiTag.textContent = 'AQI ' + air.aqi + (air.text ? ' · ' + air.text : '');
      aqiTag.className = Number(air.aqi) > 100 ? 'wx-tag is-warn' : 'wx-tag';
    }

    el('wx-station').textContent = state.stationid;
    el('wx-json').textContent = JSON.stringify(body, null, 2);

    renderTrend(data.tempchart);
    renderForecast(data);
  }

  function renderTrend(chart) {
    var box = el('wx-trend-body');
    var items = [];

    (Array.isArray(chart) ? chart : []).forEach(function (row) {
      var max = Number(row.max_temp);
      var min = Number(row.min_temp);
      var date = String(row.time || '');

      if (date && isFinite(max) && isFinite(min) && max < 9999 && min < 9999) {
        items.push({ label: date.slice(5), max: max, min: min });
      }
    });

    if (items.length < 2) {
      box.innerHTML = '<div class="wx-trend-empty">暂无趋势数据</div>';
      return;
    }

    var w = 760;
    var h = 240;
    var pad = { l: 44, r: 26, t: 36, b: 44 };
    var innerW = w - pad.l - pad.r;
    var innerH = h - pad.t - pad.b;
    var hi = Math.max.apply(null, items.map(function (d) { return d.max; }));
    var lo = Math.min.apply(null, items.map(function (d) { return d.min; }));
    var span = Math.max(1, hi - lo);
    var step = innerW / (items.length - 1);

    function px(index) {
      return pad.l + step * index;
    }

    function py(value) {
      return pad.t + innerH * (1 - (value - lo) / span);
    }

    var hiPoints = items.map(function (d, i) { return px(i) + ',' + py(d.max); }).join(' ');
    var loPoints = items.map(function (d, i) { return px(i) + ',' + py(d.min); }).join(' ');
    var area = hiPoints + ' ' + items.slice().reverse().map(function (d) {
      return px(items.indexOf(d)) + ',' + py(d.min);
    }).join(' ');

    var grid = [0, 0.5, 1].map(function (ratio) {
      var y = pad.t + innerH * ratio;
      var value = Math.round(hi - span * ratio);

      return '<line x1="' + pad.l + '" y1="' + y + '" x2="' + (w - pad.r) + '" y2="' + y + '" stroke="#eef3f2" stroke-width="1"/>'
        + '<text x="' + (pad.l - 10) + '" y="' + (y + 3) + '" text-anchor="end" font-size="10" fill="#9aabaa">' + value + '°</text>';
    }).join('');

    var dots = items.map(function (d, i) {
      return '<circle cx="' + px(i) + '" cy="' + py(d.max) + '" r="3.6" fill="#16baaa"/>'
        + '<circle cx="' + px(i) + '" cy="' + py(d.min) + '" r="3.6" fill="#6b8cff"/>';
    }).join('');

    var labels = items.map(function (d, i) {
      return '<text x="' + px(i) + '" y="' + (py(d.max) - 10) + '" text-anchor="middle" font-size="10.5" font-weight="700" fill="#0e8f83">' + Math.round(d.max) + '°</text>'
        + '<text x="' + px(i) + '" y="' + (py(d.min) + 17) + '" text-anchor="middle" font-size="10.5" fill="#6b8cff">' + Math.round(d.min) + '°</text>';
    }).join('');

    var dates = items.map(function (d, i) {
      return '<text x="' + px(i) + '" y="' + (h - 14) + '" text-anchor="middle" font-size="10" fill="#7a9291">' + escapeHtml(d.label) + '</text>';
    }).join('');

    box.innerHTML = '<svg viewBox="0 0 ' + w + ' ' + h + '" role="img" aria-label="未来气温趋势">'
      + '<defs><linearGradient id="wxTrendGrad" x1="0" y1="0" x2="0" y2="1">'
      + '<stop offset="0%" stop-color="#16baaa" stop-opacity="0.22"/>'
      + '<stop offset="100%" stop-color="#16baaa" stop-opacity="0"/>'
      + '</linearGradient></defs>'
      + grid
      + '<polygon points="' + area + '" fill="url(#wxTrendGrad)"/>'
      + '<polyline points="' + hiPoints + '" fill="none" stroke="#16baaa" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>'
      + '<polyline points="' + loPoints + '" fill="none" stroke="#6b8cff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'
      + dots
      + labels
      + dates
      + '</svg>';
  }

  function renderForecast(data) {
    var list = el('wx-forecast-list');
    var detail = (data.predict && data.predict.detail) || [];

    if (!Array.isArray(detail) || detail.length === 0) {
      list.innerHTML = '<div class="wx-forecast-empty">暂无预报数据</div>';
      return;
    }

    var today = new Date().toDateString();

    list.innerHTML = detail.map(function (item) {
      var date = new Date(String(item.date).replace(/-/g, '/') + ' 00:00:00');
      var day = item.day || {};
      var night = item.night || {};
      var dayWeather = day.weather || {};
      var nightWeather = night.weather || {};
      var isToday = !isNaN(date.getTime()) && date.toDateString() === today;

      return '<article class="wx-day' + (isToday ? ' is-today' : '') + '">'
        + '<span class="wx-day-date">' + escapeHtml(item.date ? String(item.date).slice(5) : '--') + '</span>'
        + '<span class="wx-day-week">' + (isNaN(date.getTime()) ? '—' : WEEK[date.getDay()]) + '</span>'
        + '<span class="wx-day-icon">' + iconFor(dayWeather.info) + '</span>'
        + '<span class="wx-day-text">' + escapeHtml(show(dayWeather.info)) + ' / ' + escapeHtml(show(nightWeather.info)) + '</span>'
        + '<span class="wx-day-temp">' + escapeHtml(show(dayWeather.temperature)) + '° <small>/ ' + escapeHtml(show(nightWeather.temperature)) + '°</small></span>'
        + '<span class="wx-day-rain">降水 ' + escapeHtml(show(item.precipitation, '%')) + '</span>'
        + '</article>';
    }).join('');
  }

  function initPlayer() {
    var audio = el('wx-audio');
    var card = el('wx-player');
    var button = el('wx-play');
    var bar = el('wx-progress');
    var fill = el('wx-fill');
    var time = el('wx-time');
    var status = el('wx-state');

    if (!audio || !card || !button || !bar || !fill || !time) {
      return;
    }

    var icon = button.querySelector('i');

    function format(seconds) {
      if (!isFinite(seconds) || seconds < 0) {
        return '00:00';
      }

      var min = Math.floor(seconds / 60);
      var sec = Math.floor(seconds % 60);

      return (min < 10 ? '0' : '') + min + ':' + (sec < 10 ? '0' : '') + sec;
    }

    function paint() {
      var duration = isFinite(audio.duration) ? audio.duration : 0;
      var progress = duration > 0 ? Math.min(1, audio.currentTime / duration) : 0;

      fill.style.width = (progress * 100) + '%';
      time.textContent = format(audio.currentTime) + ' / ' + format(duration);
    }

    button.addEventListener('click', function () {
      if (audio.paused) {
        audio.play().catch(function () {
          notify('音频播放失败，请检查文件或浏览器权限');
        });
      } else {
        audio.pause();
      }
    });

    audio.addEventListener('play', function () {
      card.classList.add('is-playing');
      icon.className = 'layui-icon layui-icon-pause';
      button.setAttribute('aria-label', '暂停城市电台');
      if (status) status.textContent = 'PLAYING';
    });

    audio.addEventListener('pause', function () {
      card.classList.remove('is-playing');
      icon.className = 'layui-icon layui-icon-play';
      button.setAttribute('aria-label', '播放城市电台');
      if (status) status.textContent = 'MP3';
    });

    audio.addEventListener('timeupdate', paint);
    audio.addEventListener('loadedmetadata', paint);
    audio.addEventListener('durationchange', paint);
    audio.addEventListener('ended', paint);

    audio.addEventListener('error', function () {
      if (status) status.textContent = 'ERROR';
      notify('音频加载失败');
    });

    bar.addEventListener('click', function (event) {
      if (!isFinite(audio.duration) || audio.duration <= 0) {
        return;
      }

      var rect = bar.getBoundingClientRect();
      var ratio = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));

      audio.currentTime = ratio * audio.duration;
      paint();
    });
  }

  function initNav() {
    var toggle = el('wx-nav-toggle');
    var nav = el('wx-nav');

    if (!toggle || !nav) {
      return;
    }

    var icon = toggle.querySelector('i');

    function close() {
      nav.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', '打开导航');

      if (icon) {
        icon.className = 'layui-icon layui-icon-spread-left';
      }
    }

    function open() {
      nav.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', '关闭导航');

      if (icon) {
        icon.className = 'layui-icon layui-icon-close';
      }
    }

    toggle.addEventListener('click', function (event) {
      event.stopPropagation();

      if (nav.classList.contains('is-open')) {
        close();
      } else {
        open();
      }
    });

    nav.addEventListener('click', function (event) {
      if (event.target instanceof Element && event.target.closest('a')) {
        close();
      }
    });

    document.addEventListener('click', function (event) {
      if (!nav.classList.contains('is-open')) {
        return;
      }

      if (nav.contains(event.target) || toggle.contains(event.target)) {
        return;
      }

      close();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        close();
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 820) {
        close();
      }
    });
  }

  function bindEvents() {
    var provinceEl = document.querySelector('select[name="province"]');
    var cityEl = document.querySelector('select[name="city"]');

    function handleProvince(value) {
      var province = findProvince(value);

      if (!province) {
        return;
      }

      var city = fillCities(province, null);

      if (city) {
        queryWeather(city.code, true);
      }
    }

    function handleCity(value) {
      queryWeather(value, true);
    }

    provinceEl.addEventListener('change', function () {
      handleProvince(this.value);
    });

    cityEl.addEventListener('change', function () {
      handleCity(this.value);
    });

    if (state.form) {
      state.form.on('select(wx-province)', function (data) {
        handleProvince(data.value);
      });

      state.form.on('select(wx-city)', function (data) {
        handleCity(data.value);
      });
    }

    el('wx-refresh').addEventListener('click', function () {
      queryWeather(state.stationid, true);
    });

    el('wx-toggle-json').addEventListener('click', function () {
      var wrap = el('wx-json-wrap');
      var opened = wrap.classList.toggle('is-open');

      this.innerHTML = opened
        ? '<i class="layui-icon layui-icon-up"></i> 收起'
        : '<i class="layui-icon layui-icon-down"></i> 展开';
    });

    document.querySelectorAll('.wx-nav a').forEach(function (link) {
      link.addEventListener('click', function () {
        document.querySelectorAll('.wx-nav a').forEach(function (item) {
          item.classList.remove('is-active');
        });
        this.classList.add('is-active');
      });
    });
  }

  function boot(form, layer) {
    state.form = form;
    state.layer = layer;

    bindEvents();
    initNav();
    initPlayer();

    loadProvinces()
      .then(function () {
        return queryWeather(state.stationid, true);
      })
      .catch(function (error) {
        notify(error.message);
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (window.layui && typeof window.layui.use === 'function') {
      window.layui.use(['form', 'layer'], function () {
        boot(window.layui.form, window.layui.layer);
      });

      return;
    }

    boot(null, null);
  });
})();