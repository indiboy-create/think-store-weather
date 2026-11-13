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
      || value === 9999.0
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
    var cityName = station.city || (cityEl && cityEl.options[cityEl.selectedIndex] ? cityEl.options[cityEl.selectedIndex].text : '') || cfg.defaultCity;

    el('wx-city').textContent = (station.province ? station.province + ' · ' : '') + cityName;
    el('wx-publish').textContent = real.publish_time ? '更新时间 ' + real.publish_time : '更新时间未知';
    el('wx-temp').textContent = fixed(weather.temperature, 1);
    el('wx-info').textContent = isMissing(weather.info) ? '天气现象暂无' : weather.info;
    el('wx-feel').textContent = '体感 ' + fixed(weather.feelst, 1) + '°C';
    el('wx-icon').textContent = iconFor(weather.info);

    el('wx-humidity').textContent = fixed(weather.humidity, 0, '%');
    el('wx-wind-power').textContent = isMissing(wind.direct) ? '—' : wind.direct + ' ' + show(wind.power);
    el('wx-pressure').textContent = isMissing(weather.airpressure) ? '—' : fixed(Number(weather.airpressure) / 10, 1, ' hPa');
    el('wx-rain').textContent = fixed(weather.rain, 1, ' mm');

    var windTag = el('wx-wind');
    windTag.textContent = isMissing(wind.speed) ? show(wind.power) : show(wind.power) + ' · ' + fixed(wind.speed, 1, ' m/s');

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

    renderForecast(data);
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
        ? '<i class="layui-icon layui-icon-up"></i> 收起原样返回'
        : '<i class="layui-icon layui-icon-down"></i> 展开原样返回';
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