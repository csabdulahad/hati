<?php
	
	namespace hati\cli;
	
	/**
	 * A builder class for adding flags to HatiCLI easily
	 *
	 * @since 6.0.0
	 * */
	class CLIFlagBuilder {
		
		private string $description = '';
		
		public function __construct(
			private readonly HatiCLI $cli,
			private readonly string $shotName,
			private readonly string $longName = '')	{
			
		}
		
		public function description(string $description): CLIFlagBuilder {
			$this->description = $description;
			return $this;
		}
		
		public function build(): void {
			$this->cli->addFlag([
				'shortName' => $this->shotName,
				'longName' => $this->longName,
				'description' => $this->description
			]);
		}
		
	}