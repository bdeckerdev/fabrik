/*! fabrik */
var FbThumbs=new Class({Extends:FbElement,initialize:function(a,b,c){this.field=document.id(a),this.parent(a,b),this.thumb=c,this.spinner=new Spinner(this.getContainer()),this.setupj3()},setupj3:function(){var a=this.getContainer(),b=a.getElement("button.thumb-up"),c=a.getElement("button.thumb-down");b.addEvent("click",function(a){if(a.stop(),this.options.canUse){var d=b.hasClass("btn-success")?!1:!0;this.doAjax("up",d),d?(b.addClass("btn-success"),"null"!==typeOf(c)&&c.removeClass("btn-danger")):b.removeClass("btn-success")}else this.doNoAccess()}.bind(this)),"null"!==typeOf(c)&&c.addEvent("click",function(a){if(a.stop(),this.options.canUse){var d=c.hasClass("btn-danger")?!1:!0;this.doAjax("down",d),d?(c.addClass("btn-danger"),b.removeClass("btn-success")):c.removeClass("btn-danger")}else this.doNoAccess()}.bind(this))},doAjax:function(a,b){if(b=b?!0:!1,this.options.editable===!1){this.spinner.show();var c={option:"com_fabrik",format:"raw",view:"pluginAjax",plugin:"thumbs",method:"ajax_rate",g:"element",element_id:this.options.elid,row_id:this.options.row_id,elementname:this.options.elid,userid:this.options.userid,thumb:a,listid:this.options.listid,formid:this.options.formid,add:b};new Request({url:"",data:c,onComplete:function(a){if(a=JSON.decode(a),this.spinner.hide(),a.error)console.log(a.error);else if(""!==a){var b=this.getContainer();b.getElement("button.thumb-up .thumb-count").set("text",a[0]),"null"!==typeOf(b.getElement("button.thumb-down"))&&b.getElement("button.thumb-down .thumb-count").set("text",a[1])}}.bind(this)}).send()}},doNoAccess:function(){""!==this.options.noAccessMsg&&alert(this.options.noAccessMsg)}});