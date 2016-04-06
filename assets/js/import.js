'use strict';

class Import extends NitmEntity {
	constructor() {
		super('import');
		this.forms = {
			roles: {
				create: "createImport",
				elementImport: "importElements"
			}
		};

		this.inputs = {
			roles: [
				"[role~='selectType']", "[role~='selectDataType']", "[role~='dataSource']"
			]
		};

		this.buttons = {
			create: 'newImport',
			remove: 'removeImport',
			disable: 'disableImport',
		};

		this.links = {
			source:  "[role~='importSource']"
		};

		this.views = {
			listFormContainerId: "[role~='importFormContainer']",
			containerId: "[role~='import']",
			itemId: "import",
			source: "[role~='sourceName']",
			sourceInput: "[role~='sourceNameInput']",
			preview: "[role~='previewImport']",
			element: "[role~='importElement']",
			update: "[role~='updateElement']",
		};
		this.defaultInit = [
			'initForms',
			'initSource',
			'initPreview'
		];
	}

	initSource () {
		$(this.links.source).each((i, elem) => {
			let $elem = $(elem);
			$elem.on('click', (event) =>  {
				$(this.views.source).html($elem.data('source'));
				$(this.views.sourceInput).val($elem.data('source'));
			});
		});
	}

    getContainer(containerId) {
		containerId = containerId || this.views.listFormContainerId;
		if(!$(containerId).length)
			containerId = this.views.containerId;
		return containerId;
    };

	initPreview () {
		$("form[role~='"+this.forms.roles.create+"']").on('reset', (event) => {
			$(this.views.preview).empty();
		});
	}

	afterCreate (result, currentIndex, form) {
		//Change the form to update the source since the source gets created on preview
		var message = result.message || "Success! You can import specific records in this dataset below";
		$nitm.trigger('notify', [message, this.classes.success, form]);
		if(result.success) {
			let $form = $(form),
				$container = $(this.views.containerId);
			$container.parents('.modal-dialog').removeClass('modal-sm modal-md modal-lg').addClass('modal-xlg modal-full');
			$form.attr('action', result.form.action);
			$form.data('id', result.id);
			$form.find(this.inputs.roles.join(',')).addClass('disabled').attr('disabled', true);
			$.get(result.url, (result) => {
				$container.find(this.views.preview).html(result);
			});
		}
	}

	afterPreview (result, currentIndex, form) {
		$(this.views.preview).html(result.data);
		$(form).find(':submit').text("Import");
		$(form).find("table tbody.files").empty();
	}

	afterElementImport (result, elem) {
		if(result.success || result.exists) {
			let $elem = $(elem);
			$elem.parents('tr').addClass(this.classes[result.class]);
			$elem.addClass('disabled');
			$elem.html(result.icon);
			$elem.on('click', (event) =>  { event.preventDefault(); return false});
		}
	}

	initElementImportForm (containerId){
		let $container = $nitm.getObj(containerId || 'body');
		$container.find("form[role~='"+this.forms.roles.elementImport+"']").each((i, elem) => {
			let $form = $(this);
			$form.off('submit');
			$form.on('submit', (e) =>  {
				e.preventDefault();
				if(this.hasActivity(this.id))
					return false;
				this.updateActivity(this.id);
				$form.find(this.views.element).map((i, elem) => {
					this.importElement(elem);
				});
			});
		});
	}

	initElementImport (containerId){
		let $container = $nitm.getObj(containerId || this.views.preview);
		$container.find(this.views.element).each((i, elem) => {
			let $elem = $(elem);
			$elem.on('click', (e) =>  {
				e.preventDefault();
				$nitm.trigger('start-spinner', [$elem]);
				this.importElement($elem.get(0));
			});
		});
	}

	initUpdateElement (containerId){
		let $container = $nitm.getObj(containerId || this.views.preview);
		$container.find(this.views.update).each((i, elem) => {
			let $elem = $(elem);
			$elem.on('click', (e) =>  {
				e.preventDefault();
				this.updateElement($elem.get(0));
			});
		});
	}

	importElement (elem){
		let $elem = $(elem);
		$.post($elem.attr('href'), (result) => {
			this.afterElementImport(result, $elem.get(0));
		}).always((result, status, error) => {
			$nitm.trigger('stop-spinner', [$elem]);
		}).error((result, status, error) => {
			$nitm.trigger('notify', [error, 'danger', elem]);
		});
	}

	importElements (e, form){
		e.preventDefault();
		let $form = $(form);
		return this.operation(form, function(result) {
			if(result.success) {
				$nitm.trigger('notify', [result.message, result.class, form]);
			}
		});
	}

	updateElement (elem){
		let $elem = $(elem),
			$container = $('tr[data-key="'+$elem.data('item-key')+'"]'),
			$inputs = $container.find(':input');
		let $form = $("<form method='post' action='"+$elem.attr('href')+"'>");
		$inputs.each((i, input) => {
			let $input = $(input),
				name = $input.attr('name').substr($input.attr('name').indexOf('['));
			$form.append("<input name='Element"+name+"' value='"+$input.val()+"'>")
		});
		$nitm.trigger('start-spinner', [$elem]);
		return this.operation($form.get(0), function(result) {
			if(result.success) {
				$nitm.trigger('notify', [result.message, result.class, elem]);
			}
			$nitm.trigger('stop-spinner', [$elem]);
		});
	}

	importBatch (e) {
		e.preventDefault();
		$nitm.trigger('animate-submit-start', [e.target]);
		$.post($(e.target).data('url'), function (result) {
			$nitm.trigger('animate-submit-stop', [e.target]);
			$nitm.trigger('notify', [result.message, result.class, e.target]);
			if(result.percent)
				if(result.percent < 100)
					$(e.target).text(result.percent+'% done. Import Next Batch');
				else {
					$(e.target).text('Import Complete!').removeClass().addClass('btn btn-success');
					$(e.target).on('click', function (e) {
						e.preventDefault();
						$nitm.trigger('notify', ["Import is already complete!!", "warning", e.target]);
					});
				}
		});
	}

	importAll (e) {
		e.preventDefault();
		$.post($(e.target).data('url'), (result) => {
			$nitm.trigger('notify', [result.message, result.class, e.target]);
			if(result.percent && result.percent < 100) {
				$(e.target).text(result.percent+'% done. Working..');
				this.importAll(e);
			} else {
				$(e.target).text('Import Complete!').removeClass().addClass('btn btn-success');
				$(e.target).on('click', function (e) {
					e.preventDefault();
					$nitm.trigger('notify', ["Import is already complete!!", "warning", e.target]);
				});
			}
		});
	}
}

$nitm.addOnLoadEvent(function () {
	$nitm.initModule(new Import());
});
