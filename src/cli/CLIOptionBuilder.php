<?php
	
	namespace hati\cli;
	
	use hati\util\Arr;
	
	/**
	 * A builder class for adding options to HatiCLI easily
	 *
	 * @since 6.0.0
	 * */
	class CLIOptionBuilder {
		
		private string $dataType = 'str';
		private string $description = '';
		
		private array $options = [];
		
		private bool $allowExtra = false;
		private bool $required = true;
		
		public function __construct(private readonly HatiCLI $cli, private readonly string $shortName, private readonly string $longName) {
		}
		
		public function dataType(CLIOptionFilter $dataType): CLIOptionBuilder {
			$this->dataType = $dataType->value;
			return $this;
		}
		
		public function allowValues(array|string... $options): CLIOptionBuilder {
			$this->options = Arr::varargsAsArray($options);
			return $this;
		}
		
		public function allowExtra(bool $allowExtra): CLIOptionBuilder {
			$this->allowExtra = $allowExtra;
			return $this;
		}
		
		public function required(bool $required): CLIOptionBuilder {
			$this->required = $required;
			return $this;
		}
		
		public function description(string $description): CLIOptionBuilder {
			$this->description = $description;
			return $this;
		}
		
		/**
		 * Add the option built by method chaining on this object to HatiCLI
		 * */
		public function build(): void {
			$this->cli->addOption( [
				'shortName' => $this->shortName,
				'longName' => $this->longName,
				'type' => $this->dataType,
				'options' => $this->options,
				'allow_extra' => $this->allowExtra,
				'required' => $this->required,
				'description' => $this->description
			]);
		}
		
	}