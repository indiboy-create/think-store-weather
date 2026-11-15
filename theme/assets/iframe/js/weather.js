(function () {
  'use strict';

  var body = document.body;

  var cfg = {
    api: body.getAttribute('data-api') || '',
    station: body.getAttribute('data-station') || '',
    province: body.getAttribute('data-province') || '',
    city: body.getAttribute('data-city') || ''
  };

  var state = {
    tree: [],
    stationid: cfg.station,
    loading: false,
    form: null,
    table: null,
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
      return response.json().then(function (payload) {
        if (!response.ok) {
          throw new Error(payload && payload.msg ? payload.msg : '请求失败，HTTP ' + response.status);
        }

        return payload;
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

    if (!cityEl) {
      return null;
    }

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
    var provinceEl = document.querySelector('select[name="province"]');

    if (!provinceEl) {
      return Promise.resolve();
    }

    return getJSON(cfg.api + '/provinces').then(function (tree) {
      state.tree = Array.isArray(tree) ? tree : [];

      provinceEl.innerHTML = state.tree.map(function (province) {
        return '<option value="' + escapeHtml(province.id) + '">' + escapeHtml(province.title) + '</option>';
      }).join('');

      var province = null;

      for (var i = 0; i < state.tree.length; i++) {
        if (state.tree[i].title === cfg.province) {
          province = state.tree[i];
          break;
        }
      }

      province = province || state.tree[0];

      if (province) {
        provinceEl.value = String(province.id);
        fillCities(province, cfg.station);
      }

      renderSelects();
    });
  }

  function setLoading(loading) {
    state.loading = loading;

    var button = el('iframe-refresh');

    if (button) {
      button.disabled = loading;
      button.className = loading
        ? 'layui-btn layui-btn-normal layui-btn-sm layui-btn-disabled'
        : 'layui-btn layui-btn-normal layui-btn-sm';
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

    return getJSON(cfg.api + '/weather?stationid=' + encodeURIComponent(code))
      .then(function (payload) {
        state.stationid = code;
        state.data = payload;
        render(payload);
      })
      .catch(function (error) {
        notify(error.message);
      })
      .then(function () {
        setLoading(false);
      });
  }

  function render(payload) {
    var data = (payload && payload.data) || {};
    var real = data.real || {};
    var weather = real.weather || {};
    var wind = real.wind || {};
    var air = data.air || {};
    var station = real.station || {};
    var cityEl = document.querySelector('select[name="city"]');
    var cityName = station.city
      || (cityEl && cityEl.selectedIndex >= 0 ? cityEl.options[cityEl.selectedIndex].text : '')
      || cfg.city;

    el('iframe-city').textContent = cityName;
    el('iframe-publish').textContent = real.publish_time ? '更新于 ' + real.publish_time : '更新时间未知';
    el('iframe-temp').textContent = fixed(weather.temperature, 1);
    el('iframe-info').textContent = isMissing(weather.info) ? '天气现象暂无' : weather.info;
    el('iframe-feel').textContent = fixed(weather.feelst, 1);
    el('iframe-icon').textContent = iconFor(weather.info);
    el('iframe-humidity').textContent = fixed(weather.humidity, 0, '%');
    el('iframe-wind').textContent = isMissing(wind.direct) ? '—' : wind.direct + ' ' + show(wind.power);
    el('iframe-pressure').textContent = isMissing(weather.airpressure)
      ? '—'
      : fixed(Number(weather.airpressure) / 10, 1, ' hPa');
    el('iframe-rain').textContent = fixed(weather.rain, 1, ' mm');
    el('iframe-aqi').textContent = isMissing(air.aqi)
      ? '—'
      : air.aqi + (air.text ? ' · ' + air.text : '');
    el('iframe-time').textContent = real.publish_time || '—';
    el('iframe-station').textContent = state.stationid;

    renderForecast(data);
  }

  function forecastRows(data) {
    var detail = (data.predict && data.predict.detail) || [];

    if (!Array.isArray(detail)) {
      return [];
    }

    return detail.map(function (item) {
      var date = new Date(String(item.date).replace(/-/g, '/') + ' 00:00:00');
      var day = item.day || {};
      var night = item.night || {};
      var dayWeather = day.weather || {};
      var nightWeather = night.weather || {};

      return {
        date: item.date ? String(item.date).slice(5) : '--',
        week: isNaN(date.getTime()) ? '—' : WEEK[date.getDay()],
        day: show(dayWeather.info),
        night: show(nightWeather.info),
        high: show(dayWeather.temperature),
        low: show(nightWeather.temperature),
        rain: show(item.precipitation, '%')
      };
    });
  }

  function renderForecast(data) {
    if (!state.table) {
      return;
    }

    state.table.render({
      elem: '#iframe-forecast',
      id: 'iframe-forecast',
      data: forecastRows(data),
      page: false,
      even: true,
      text: { none: '暂无预报数据' },
      cols: [[
        { field: 'date', title: '日期', width: 100 },
        { field: 'week', title: '星期', width: 90 },
        { field: 'day', title: '白天', minWidth: 110 },
        { field: 'night', title: '夜间', minWidth: 110 },
        {
          field: 'high',
          title: '最高气温',
          width: 110,
          templet: function (row) {
            return escapeHtml(row.high) + '°';
          }
        },
        {
          field: 'low',
          title: '最低气温',
          width: 110,
          templet: function (row) {
            return escapeHtml(row.low) + '°';
          }
        },
        { field: 'rain', title: '降水概率', width: 110 }
      ]]
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

    if (provinceEl) {
      provinceEl.addEventListener('change', function () {
        handleProvince(this.value);
      });
    }

    if (cityEl) {
      cityEl.addEventListener('change', function () {
        handleCity(this.value);
      });
    }

    if (state.form) {
      state.form.on('select(iframe-province)', function (data) {
        handleProvince(data.value);
      });

      state.form.on('select(iframe-city)', function (data) {
        handleCity(data.value);
      });
    }

    var refresh = el('iframe-refresh');

    if (refresh) {
      refresh.addEventListener('click', function () {
        queryWeather(state.stationid, true);
      });
    }
  }

  function boot(form, table, layer) {
    state.form = form;
    state.table = table;
    state.layer = layer;

    bindEvents();

    if (window.layui && window.layui.element) {
      window.layui.element.on('tab(iframe-weather-tabs)', function () {
        window.setTimeout(function () {
          if (state.table && typeof state.table.resize === 'function') {
            state.table.resize('iframe-forecast');
          }
        }, 0);
      });
    }

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
      window.layui.use(['form', 'table', 'layer', 'element'], function () {
        boot(window.layui.form, window.layui.table, window.layui.layer);
      });

      return;
    }

    boot(null, null, null);
  });
})();