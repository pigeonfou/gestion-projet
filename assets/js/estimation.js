(() => {
  'use strict';
  window.ProjectFlow = window.ProjectFlow || {};
  window.ProjectFlow.estimationDecimal = value => {
    const text=String(value ?? '').trim().replace(',', '.');
    if(!/^(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/.test(text)) return 0;
    const number=Number(text);return Number.isFinite(number) && number>=0 ? number : 0;
  };
  window.ProjectFlow.estimationTotal = (quantity,unit) => {
    const total=quantity*unit;
    return Number.isFinite(total)?Math.round((total+Number.EPSILON*Math.max(1,total))*100)/100:0;
  };
})();
