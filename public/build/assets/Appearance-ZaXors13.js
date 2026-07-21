import{d as u,aj as y,o as t,a as l,F as f,h as g,j as m,f as n,z as h,I as x,t as b,u as r,e as d,b as e,m as v}from"./app-B_lr7Zzc.js";import{c as o}from"./utils-CIklQGH3.js";import{_ as I,a as M}from"./Layout.vue_vue_type_script_setup_true_lang-DCHL2iE-.js";import{_ as w}from"./AppLayout.vue_vue_type_script_setup_true_lang-B_e86K8u.js";import"./Button.vue_vue_type_script_setup_true_lang-B6tHi_Xl.js";import"./index-B0ZLSH1p.js";import"./chevron-right-CkRF2NUs.js";import"./x-KVMfrfbR.js";import"./AppLogoIcon.vue_vue_type_script_setup_true_lang-DKr75rUq.js";import"./users-DW4XQ638.js";import"./building-2-BKWhlmF_.js";/**
 * @license lucide-vue-next v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const A=o("MonitorIcon",[["rect",{width:"20",height:"14",x:"2",y:"3",rx:"2",key:"48i651"}],["line",{x1:"8",x2:"16",y1:"21",y2:"21",key:"1svkeh"}],["line",{x1:"12",x2:"12",y1:"17",y2:"21",key:"vw1qmm"}]]);/**
 * @license lucide-vue-next v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const C=o("MoonIcon",[["path",{d:"M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z",key:"a7tn18"}]]);/**
 * @license lucide-vue-next v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const $=o("SunIcon",[["circle",{cx:"12",cy:"12",r:"4",key:"4exip2"}],["path",{d:"M12 2v2",key:"tus03m"}],["path",{d:"M12 20v2",key:"1lh1kg"}],["path",{d:"m4.93 4.93 1.41 1.41",key:"149t6j"}],["path",{d:"m17.66 17.66 1.41 1.41",key:"ptbguv"}],["path",{d:"M2 12h2",key:"1t8f8n"}],["path",{d:"M20 12h2",key:"1q8mjw"}],["path",{d:"m6.34 17.66-1.41 1.41",key:"1m8zz5"}],["path",{d:"m19.07 4.93-1.41 1.41",key:"1shlcs"}]]),j=["onClick"],z={class:"ml-1.5 text-sm"},B=u({__name:"AppearanceTabs",props:{class:{default:""}},setup(c){const{appearance:a,updateAppearance:p}=y(),i=[{value:"light",Icon:$,label:"Light"},{value:"dark",Icon:C,label:"Dark"},{value:"system",Icon:A,label:"System"}];return(D,L)=>(t(),l("div",{class:m(["inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800",c.class])},[(t(),l(f,null,g(i,({value:s,Icon:_,label:k})=>n("button",{key:s,onClick:q=>r(p)(s),class:m(["flex items-center rounded-md px-3.5 py-1.5 transition-colors",r(a)===s?"bg-white shadow-sm dark:bg-neutral-700 dark:text-neutral-100":"text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60"])},[(t(),h(x(_),{class:"-ml-1 h-4 w-4"})),n("span",z,b(k),1)],10,j)),64))],2))}}),S={class:"space-y-6"},O=u({__name:"Appearance",setup(c){const a=[{title:"Appearance settings",href:"/settings/appearance"}];return(p,i)=>(t(),h(w,{breadcrumbs:a},{default:d(()=>[e(r(v),{title:"Appearance settings"}),e(I,null,{default:d(()=>[n("div",S,[e(M,{title:"Appearance settings",description:"Update your account's appearance settings"}),e(B)])]),_:1})]),_:1}))}});export{O as default};
